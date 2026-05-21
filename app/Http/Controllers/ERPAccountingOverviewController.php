<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\CashIn;
use App\Models\CashOut;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ERPAccountingOverviewController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedYear = (int) $request->integer('year', now()->year);
        $companyId = ErpCompanyResolver::resolveForReporting($request);
        [$dateFrom, $dateTo] = $this->resolveDateRange($request, $selectedYear);

        $periodCashInTotal = (float) $this->applyDateRange($this->cashInBaseQuery($companyId), $dateFrom, $dateTo)
            ->sum('amount');
        $periodCashOutTotal = (float) $this->applyDateRange($this->cashOutBaseQuery($companyId), $dateFrom, $dateTo)
            ->sum('amount');

        $cashBalanceByAccount = $this->cashBalanceByAccount($companyId);
        $companySummaries = $this->companySummaries($companyId, $dateFrom, $dateTo);
        $transactionBreakdown = $this->transactionBreakdown($companyId, $dateFrom, $dateTo);

        return Inertia::render('ERP/Accounting/Overview', [
            'selected_year' => $selectedYear,
            'filters' => [
                'company_id' => $request->query('company_id', $companyId ?? ErpCompanyResolver::ALL_COMPANIES),
                'date_from' => $dateFrom?->format('Y-m-d'),
                'date_to' => $dateTo?->format('Y-m-d'),
            ],
            'stats' => [
                'cash_in_year' => $periodCashInTotal,
                'cash_out_year' => $periodCashOutTotal,
                'net_year' => $periodCashInTotal - $periodCashOutTotal,
                'cash_balance' => (float) $cashBalanceByAccount->sum('balance'),
                'cash_account_count' => $cashBalanceByAccount->count(),
                'company_count' => $companySummaries->count(),
            ],
            'monthly_data' => $this->monthlyData($companyId, $dateFrom, $dateTo),
            'cash_balance_chart' => $cashBalanceByAccount
                ->take(8)
                ->map(fn (array $row): array => [
                    'label' => $row['account_label'],
                    'value' => max($row['balance'], 0),
                ])
                ->values()
                ->all(),
            'cash_accounts' => $cashBalanceByAccount->take(8)->values()->all(),
            'company_summaries' => $companySummaries->take(8)->values()->all(),
            'transaction_breakdown' => $transactionBreakdown['chart'],
            'transaction_highlights' => $transactionBreakdown['highlights'],
        ]);
    }

    private function cashInBaseQuery(?int $companyId)
    {
        return CashIn::query()
            ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn ($jq) => $jq->where('company_id', $companyId)));
    }

    private function cashOutBaseQuery(?int $companyId)
    {
        return CashOut::query()
            ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn ($jq) => $jq->where('company_id', $companyId)));
    }

    private function resolveDateRange(Request $request, int $selectedYear): array
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->string('date_from')->toString())->startOfDay()
            : Carbon::create($selectedYear, 1, 1)->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->string('date_to')->toString())->endOfDay()
            : Carbon::create($selectedYear, 12, 31)->endOfDay();

        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$dateFrom, $dateTo];
    }

    private function applyDateRange($query, ?Carbon $dateFrom, ?Carbon $dateTo)
    {
        return $query
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo->toDateString()));
    }

    /**
     * @return list<array{month:int,income:float,expense:float,net:float}>
     */
    private function monthlyData(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $incomeRows = $this->applyDateRange($this->cashInBaseQuery($companyId), $dateFrom, $dateTo)
            ->get(['date', 'amount']);

        $expenseRows = $this->applyDateRange($this->cashOutBaseQuery($companyId), $dateFrom, $dateTo)
            ->get(['date', 'amount']);

        $incomeByMonth = $incomeRows
            ->groupBy(fn ($row) => (int) $row->date?->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $expenseByMonth = $expenseRows
            ->groupBy(fn ($row) => (int) $row->date?->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        return collect(range(1, 12))
            ->map(function (int $month) use ($incomeByMonth, $expenseByMonth): array {
                $income = (float) ($incomeByMonth[$month] ?? 0);
                $expense = (float) ($expenseByMonth[$month] ?? 0);

                return [
                    'month' => $month,
                    'income' => $income,
                    'expense' => $expense,
                    'net' => $income - $expense,
                ];
            })
            ->all();
    }

    /**
     * @return Collection<int, array{account_id:int,account_label:string,income:float,expense:float,balance:float}>
     */
    private function cashBalanceByAccount(?int $companyId): Collection
    {
        $incomeByAccount = $this->cashInBaseQuery($companyId)
            ->whereNotNull('cash_account_id')
            ->selectRaw('cash_account_id, SUM(amount) as total')
            ->groupBy('cash_account_id')
            ->pluck('total', 'cash_account_id');

        $expenseByAccount = $this->cashOutBaseQuery($companyId)
            ->whereNotNull('cash_account_id')
            ->selectRaw('cash_account_id, SUM(amount) as total')
            ->groupBy('cash_account_id')
            ->pluck('total', 'cash_account_id');

        $accountIds = collect($incomeByAccount->keys())
            ->merge($expenseByAccount->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($accountIds->isEmpty()) {
            return collect();
        }

        $accounts = Account::query()
            ->whereIn('id', $accountIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        return $accountIds
            ->map(function (int $accountId) use ($accounts, $incomeByAccount, $expenseByAccount): array {
                $account = $accounts->get($accountId);
                $income = (float) ($incomeByAccount[$accountId] ?? 0);
                $expense = (float) ($expenseByAccount[$accountId] ?? 0);

                return [
                    'account_id' => $accountId,
                    'account_label' => $account ? $account->displayLabel() : 'Akun kas/bank tidak dikenal',
                    'income' => $income,
                    'expense' => $expense,
                    'balance' => $income - $expense,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['balance'])
            ->values();
    }

    /**
     * @return Collection<int, array{company_id:int|null,company_name:string,cash_in_year:float,cash_out_year:float,net_year:float}>
     */
    private function companySummaries(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        $cashInRows = DB::table('cash_in')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'cash_in.journal_entry_id')
            ->leftJoin('companies', 'companies.id', '=', 'journal_entries.company_id')
            ->when($companyId, fn ($q) => $q->where('journal_entries.company_id', $companyId))
            ->when($dateFrom, fn ($q) => $q->whereDate('cash_in.date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('cash_in.date', '<=', $dateTo->toDateString()))
            ->groupBy('journal_entries.company_id', 'companies.name')
            ->select(
                'journal_entries.company_id',
                DB::raw("COALESCE(companies.name, 'Belum ditentukan') as company_name"),
                DB::raw('SUM(cash_in.amount) as total_in')
            )
            ->get();

        $cashOutRows = DB::table('cash_out')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'cash_out.journal_entry_id')
            ->leftJoin('companies', 'companies.id', '=', 'journal_entries.company_id')
            ->when($companyId, fn ($q) => $q->where('journal_entries.company_id', $companyId))
            ->when($dateFrom, fn ($q) => $q->whereDate('cash_out.date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('cash_out.date', '<=', $dateTo->toDateString()))
            ->groupBy('journal_entries.company_id', 'companies.name')
            ->select(
                'journal_entries.company_id',
                DB::raw("COALESCE(companies.name, 'Belum ditentukan') as company_name"),
                DB::raw('SUM(cash_out.amount) as total_out')
            )
            ->get();

        $summaryMap = [];

        foreach ($cashInRows as $row) {
            $key = $row->company_id !== null ? (string) $row->company_id : 'null';
            $summaryMap[$key] = [
                'company_id' => $row->company_id ? (int) $row->company_id : null,
                'company_name' => (string) $row->company_name,
                'cash_in_year' => (float) $row->total_in,
                'cash_out_year' => 0.0,
                'net_year' => (float) $row->total_in,
            ];
        }

        foreach ($cashOutRows as $row) {
            $key = $row->company_id !== null ? (string) $row->company_id : 'null';
            $existing = $summaryMap[$key] ?? [
                'company_id' => $row->company_id ? (int) $row->company_id : null,
                'company_name' => (string) $row->company_name,
                'cash_in_year' => 0.0,
                'cash_out_year' => 0.0,
                'net_year' => 0.0,
            ];

            $existing['cash_out_year'] = (float) $row->total_out;
            $existing['net_year'] = $existing['cash_in_year'] - $existing['cash_out_year'];
            $summaryMap[$key] = $existing;
        }

        return collect($summaryMap)
            ->sortByDesc(fn (array $row) => $row['net_year'])
            ->values();
    }

    /**
     * @return array{
     *     chart: array{labels:list<string>,datasets:list<array<string,mixed>>},
     *     highlights: list<array{label:string,income:float,expense:float}>
     * }
     */
    private function transactionBreakdown(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $incomeRows = DB::table('cash_in')
            ->leftJoin('cash_categories', function ($join): void {
                $join->on('cash_categories.key', '=', 'cash_in.category')
                    ->where('cash_categories.domain', '=', 'cash_in');
            })
            ->when($companyId, fn ($q) => $q->where('journal_entries.company_id', $companyId))
            ->when($dateFrom, fn ($q) => $q->whereDate('cash_in.date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('cash_in.date', '<=', $dateTo->toDateString()))
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'cash_in.journal_entry_id')
            ->groupBy('cash_in.category', 'cash_categories.label')
            ->select(
                'cash_in.category',
                DB::raw('COALESCE(cash_categories.label, cash_in.category) as label'),
                DB::raw('SUM(cash_in.amount) as total')
            )
            ->get();

        $expenseRows = DB::table('cash_out')
            ->leftJoin('cash_categories', function ($join): void {
                $join->on('cash_categories.key', '=', 'cash_out.category')
                    ->where('cash_categories.domain', '=', 'cash_out');
            })
            ->when($companyId, fn ($q) => $q->where('journal_entries.company_id', $companyId))
            ->when($dateFrom, fn ($q) => $q->whereDate('cash_out.date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('cash_out.date', '<=', $dateTo->toDateString()))
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'cash_out.journal_entry_id')
            ->groupBy('cash_out.category', 'cash_categories.label')
            ->select(
                'cash_out.category',
                DB::raw('COALESCE(cash_categories.label, cash_out.category) as label'),
                DB::raw('SUM(cash_out.amount) as total')
            )
            ->get();

        $bucket = [];

        foreach ($incomeRows as $row) {
            $key = (string) $row->category;
            $bucket[$key] = [
                'label' => $this->humanizeCategoryLabel((string) $row->label),
                'income' => (float) $row->total,
                'expense' => 0.0,
            ];
        }

        foreach ($expenseRows as $row) {
            $key = (string) $row->category;
            $existing = $bucket[$key] ?? [
                'label' => $this->humanizeCategoryLabel((string) $row->label),
                'income' => 0.0,
                'expense' => 0.0,
            ];
            $existing['expense'] = (float) $row->total;
            $bucket[$key] = $existing;
        }

        $highlights = collect($bucket)
            ->sortByDesc(fn (array $row) => max($row['income'], $row['expense']))
            ->take(6)
            ->values();

        return [
            'chart' => [
                'labels' => $highlights->pluck('label')->all(),
                'datasets' => [
                    [
                        'label' => 'Pemasukan',
                        'data' => $highlights->pluck('income')->all(),
                    ],
                    [
                        'label' => 'Pengeluaran',
                        'data' => $highlights->pluck('expense')->all(),
                    ],
                ],
            ],
            'highlights' => $highlights
                ->map(fn (array $row): array => [
                    'label' => $row['label'],
                    'income' => $row['income'],
                    'expense' => $row['expense'],
                ])
                ->all(),
        ];
    }

    private function humanizeCategoryLabel(string $label): string
    {
        $normalized = trim($label);

        if ($normalized === '') {
            return 'Lainnya';
        }

        if (str_contains($normalized, '_')) {
            return str($normalized)->replace('_', ' ')->title()->toString();
        }

        return $normalized;
    }
}
