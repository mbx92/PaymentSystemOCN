<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Core\Models\Company;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\AccountingInventoryRecord;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\PosSale;
use Illuminate\Database\Eloquent\Builder;
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
        $posRows = $this->posOverviewRows($companyId, $dateFrom, $dateTo);
        $supplierPaymentRows = $this->supplierPaymentOverviewRows($companyId, $dateFrom, $dateTo);
        $inventoryRows = $this->inventoryOverviewRows($companyId, $dateFrom, $dateTo);
        $openingCashByAccount = $this->openingCashBalanceByAccount($companyId, $dateFrom);

        $periodCashInTotal = (float) $this->applyDateRange($this->cashInBaseQuery($companyId), $dateFrom, $dateTo)
            ->sum('amount')
            + (float) $posRows->where('direction', 'in')->sum('amount');
        $periodCashOutTotal = (float) $this->applyDateRange($this->cashOutBaseQuery($companyId), $dateFrom, $dateTo)
            ->sum('amount')
            + (float) $posRows->where('direction', 'out')->sum('amount')
            + (float) $supplierPaymentRows->sum('amount')
            + (float) $inventoryRows->sum('amount');

        $cashBalanceByAccount = $this->cashBalanceByAccount($companyId, $dateFrom, $dateTo, $openingCashByAccount, $posRows, $supplierPaymentRows, $inventoryRows);
        $companySummaries = $this->companySummaries($companyId, $dateFrom, $dateTo, $posRows, $supplierPaymentRows, $inventoryRows);
        $transactionBreakdown = $this->transactionBreakdown($companyId, $dateFrom, $dateTo, $posRows, $supplierPaymentRows, $inventoryRows);
        $openingCashBalance = (float) $cashBalanceByAccount->sum('opening_balance');
        $endingCashBalance = (float) $cashBalanceByAccount->sum('ending_balance');

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
                'opening_cash_balance' => $openingCashBalance,
                'cash_balance' => $endingCashBalance,
                'ending_cash_balance' => $endingCashBalance,
                'cash_account_count' => $cashBalanceByAccount->count(),
                'company_count' => $companySummaries->count(),
            ],
            'monthly_data' => $this->monthlyData($companyId, $dateFrom, $dateTo, $posRows, $supplierPaymentRows, $inventoryRows),
            'cash_balance_chart' => $cashBalanceByAccount
                ->take(8)
                ->map(fn (array $row): array => [
                    'label' => $row['account_label'],
                    'value' => max($row['ending_balance'], 0),
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
            ->when($companyId, fn (Builder $q) => $this->applyCompanyScope($q, $companyId));
    }

    private function cashOutBaseQuery(?int $companyId)
    {
        return CashOut::query()
            ->when($companyId, fn (Builder $q) => $this->applyCompanyScope($q, $companyId));
    }

    private function applyCompanyScope(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $scope) use ($companyId): void {
            $scope->whereHas('journalEntry', fn (Builder $journal) => $journal->where('company_id', $companyId))
                ->orWhere(function (Builder $fallback) use ($companyId): void {
                    $fallback->whereHas('creator', fn (Builder $creator) => $creator->where('company_id', $companyId))
                        ->where(function (Builder $journalState): void {
                            $journalState->whereNull('journal_entry_id')
                                ->orWhereHas('journalEntry', fn (Builder $journal) => $journal->whereNull('company_id'));
                        });
                });
        });
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
    private function monthlyData(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo, Collection $posRows, Collection $supplierPaymentRows, Collection $inventoryRows): array
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

        $posIncomeByMonth = $posRows
            ->where('direction', 'in')
            ->groupBy(fn (array $row) => (int) Carbon::parse($row['date'])->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $posExpenseByMonth = $posRows
            ->where('direction', 'out')
            ->groupBy(fn (array $row) => (int) Carbon::parse($row['date'])->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $supplierExpenseByMonth = $supplierPaymentRows
            ->groupBy(fn (array $row) => (int) Carbon::parse($row['date'])->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
        $inventoryExpenseByMonth = $inventoryRows
            ->groupBy(fn (array $row) => (int) Carbon::parse($row['date'])->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        return collect(range(1, 12))
            ->map(function (int $month) use ($incomeByMonth, $expenseByMonth, $posIncomeByMonth, $posExpenseByMonth, $supplierExpenseByMonth, $inventoryExpenseByMonth): array {
                $income = (float) ($incomeByMonth[$month] ?? 0) + (float) ($posIncomeByMonth[$month] ?? 0);
                $expense = (float) ($expenseByMonth[$month] ?? 0)
                    + (float) ($posExpenseByMonth[$month] ?? 0)
                    + (float) ($supplierExpenseByMonth[$month] ?? 0)
                    + (float) ($inventoryExpenseByMonth[$month] ?? 0);

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
    private function cashBalanceByAccount(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo, Collection $openingCashByAccount, Collection $posRows, Collection $supplierPaymentRows, Collection $inventoryRows): Collection
    {
        $incomeByAccount = $this->applyDateRange($this->cashInBaseQuery($companyId), $dateFrom, $dateTo)
            ->whereNotNull('cash_account_id')
            ->selectRaw('cash_account_id, SUM(amount) as total')
            ->groupBy('cash_account_id')
            ->pluck('total', 'cash_account_id');

        $expenseByAccount = $this->applyDateRange($this->cashOutBaseQuery($companyId), $dateFrom, $dateTo)
            ->whereNotNull('cash_account_id')
            ->selectRaw('cash_account_id, SUM(amount) as total')
            ->groupBy('cash_account_id')
            ->pluck('total', 'cash_account_id');

        $posIncomeByAccount = $posRows
            ->where('direction', 'in')
            ->whereNotNull('cash_account_id')
            ->groupBy('cash_account_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $posExpenseByAccount = $posRows
            ->where('direction', 'out')
            ->whereNotNull('cash_account_id')
            ->groupBy('cash_account_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $supplierExpenseByAccount = $supplierPaymentRows
            ->whereNotNull('cash_account_id')
            ->groupBy('cash_account_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
        $inventoryExpenseByAccount = $inventoryRows
            ->whereNotNull('cash_account_id')
            ->groupBy('cash_account_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $accountIds = collect($openingCashByAccount->keys())
            ->merge($incomeByAccount->keys())
            ->merge($expenseByAccount->keys())
            ->merge($posIncomeByAccount->keys())
            ->merge($posExpenseByAccount->keys())
            ->merge($supplierExpenseByAccount->keys())
            ->merge($inventoryExpenseByAccount->keys())
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

        $posAccountLabels = $posRows
            ->whereNotNull('cash_account_id')
            ->pluck('cash_account_label', 'cash_account_id');

        $supplierAccountLabels = $supplierPaymentRows
            ->whereNotNull('cash_account_id')
            ->pluck('cash_account_label', 'cash_account_id');
        $inventoryAccountLabels = $inventoryRows
            ->whereNotNull('cash_account_id')
            ->pluck('cash_account_label', 'cash_account_id');

        return $accountIds
            ->map(function (int $accountId) use ($accounts, $openingCashByAccount, $incomeByAccount, $expenseByAccount, $posIncomeByAccount, $posExpenseByAccount, $supplierExpenseByAccount, $inventoryExpenseByAccount, $posAccountLabels, $supplierAccountLabels, $inventoryAccountLabels): array {
                $account = $accounts->get($accountId);
                $openingBalance = (float) ($openingCashByAccount[$accountId] ?? 0);
                $income = (float) ($incomeByAccount[$accountId] ?? 0) + (float) ($posIncomeByAccount[$accountId] ?? 0);
                $expense = (float) ($expenseByAccount[$accountId] ?? 0)
                    + (float) ($posExpenseByAccount[$accountId] ?? 0)
                    + (float) ($supplierExpenseByAccount[$accountId] ?? 0)
                    + (float) ($inventoryExpenseByAccount[$accountId] ?? 0);
                $endingBalance = $openingBalance + $income - $expense;

                return [
                    'account_id' => $accountId,
                    'account_label' => $account?->displayLabel()
                        ?? (string) ($posAccountLabels[$accountId] ?? $supplierAccountLabels[$accountId] ?? $inventoryAccountLabels[$accountId] ?? 'Akun kas/bank tidak dikenal'),
                    'opening_balance' => $openingBalance,
                    'income' => $income,
                    'expense' => $expense,
                    'ending_balance' => $endingBalance,
                    'balance' => $endingBalance,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['ending_balance'])
            ->values();
    }

    /**
     * @return Collection<int, float>
     */
    private function openingCashBalanceByAccount(?int $companyId, Carbon $dateFrom): Collection
    {
        return JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('accounts.is_cash_bank', true)
            ->where('accounts.type', 'asset')
            ->where('journal_entries.status', DocumentStatus::Posted->value)
            ->when($companyId, fn ($q) => $q->where('journal_entries.company_id', $companyId))
            ->whereDate('journal_entries.entry_date', '<', $dateFrom->toDateString())
            ->groupBy('journal_lines.account_id')
            ->pluck(DB::raw('SUM(journal_lines.debit - journal_lines.credit) as opening_balance'), 'journal_lines.account_id')
            ->map(fn ($balance): float => (float) $balance);
    }

    /**
     * @return Collection<int, array{company_id:int|null,company_name:string,cash_in_year:float,cash_out_year:float,net_year:float}>
     */
    private function companySummaries(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo, Collection $posRows, Collection $supplierPaymentRows, Collection $inventoryRows): Collection
    {
        $resolvedCompanyIdSql = 'COALESCE(journal_entries.company_id, users.company_id)';
        $resolvedCompanyNameSql = "COALESCE(journal_companies.name, user_companies.name, 'Belum ditentukan')";

        $cashInRows = DB::table('cash_in')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'cash_in.journal_entry_id')
            ->leftJoin('users', 'users.id', '=', 'cash_in.created_by')
            ->leftJoin('companies as journal_companies', 'journal_companies.id', '=', 'journal_entries.company_id')
            ->leftJoin('companies as user_companies', 'user_companies.id', '=', 'users.company_id')
            ->when($companyId, fn ($q) => $q->whereRaw($resolvedCompanyIdSql.' = ?', [$companyId]))
            ->when($dateFrom, fn ($q) => $q->whereDate('cash_in.date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('cash_in.date', '<=', $dateTo->toDateString()))
            ->groupBy(DB::raw($resolvedCompanyIdSql), 'journal_companies.name', 'user_companies.name')
            ->select(
                DB::raw($resolvedCompanyIdSql.' as company_id'),
                DB::raw($resolvedCompanyNameSql.' as company_name'),
                DB::raw('SUM(cash_in.amount) as total_in')
            )
            ->get();

        $cashOutRows = DB::table('cash_out')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'cash_out.journal_entry_id')
            ->leftJoin('users', 'users.id', '=', 'cash_out.created_by')
            ->leftJoin('companies as journal_companies', 'journal_companies.id', '=', 'journal_entries.company_id')
            ->leftJoin('companies as user_companies', 'user_companies.id', '=', 'users.company_id')
            ->when($companyId, fn ($q) => $q->whereRaw($resolvedCompanyIdSql.' = ?', [$companyId]))
            ->when($dateFrom, fn ($q) => $q->whereDate('cash_out.date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('cash_out.date', '<=', $dateTo->toDateString()))
            ->groupBy(DB::raw($resolvedCompanyIdSql), 'journal_companies.name', 'user_companies.name')
            ->select(
                DB::raw($resolvedCompanyIdSql.' as company_id'),
                DB::raw($resolvedCompanyNameSql.' as company_name'),
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

        foreach ($posRows as $row) {
            $key = $row['company_id'] !== null ? (string) $row['company_id'] : 'null';
            $existing = $summaryMap[$key] ?? [
                'company_id' => $row['company_id'],
                'company_name' => $row['company_name'],
                'cash_in_year' => 0.0,
                'cash_out_year' => 0.0,
                'net_year' => 0.0,
            ];

            if ($row['direction'] === 'in') {
                $existing['cash_in_year'] += (float) $row['amount'];
            } else {
                $existing['cash_out_year'] += (float) $row['amount'];
            }

            $existing['net_year'] = $existing['cash_in_year'] - $existing['cash_out_year'];
            $summaryMap[$key] = $existing;
        }

        foreach ($supplierPaymentRows as $row) {
            $key = $row['company_id'] !== null ? (string) $row['company_id'] : 'null';
            $existing = $summaryMap[$key] ?? [
                'company_id' => $row['company_id'],
                'company_name' => $row['company_name'],
                'cash_in_year' => 0.0,
                'cash_out_year' => 0.0,
                'net_year' => 0.0,
            ];

            $existing['cash_out_year'] += (float) $row['amount'];
            $existing['net_year'] = $existing['cash_in_year'] - $existing['cash_out_year'];
            $summaryMap[$key] = $existing;
        }

        foreach ($inventoryRows as $row) {
            $key = $row['company_id'] !== null ? (string) $row['company_id'] : 'null';
            $existing = $summaryMap[$key] ?? [
                'company_id' => $row['company_id'],
                'company_name' => $row['company_name'],
                'cash_in_year' => 0.0,
                'cash_out_year' => 0.0,
                'net_year' => 0.0,
            ];

            $existing['cash_out_year'] += (float) $row['amount'];
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
    private function transactionBreakdown(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo, Collection $posRows, Collection $supplierPaymentRows, Collection $inventoryRows): array
    {
        $incomeRows = DB::table('cash_in')
            ->leftJoin('cash_categories', function ($join): void {
                $join->on('cash_categories.key', '=', 'cash_in.category')
                    ->where('cash_categories.domain', '=', 'cash_in');
            })
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'cash_in.journal_entry_id')
            ->leftJoin('users', 'users.id', '=', 'cash_in.created_by')
            ->when($companyId, fn ($q) => $q->whereRaw('COALESCE(journal_entries.company_id, users.company_id) = ?', [$companyId]))
            ->when($dateFrom, fn ($q) => $q->whereDate('cash_in.date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('cash_in.date', '<=', $dateTo->toDateString()))
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
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'cash_out.journal_entry_id')
            ->leftJoin('users', 'users.id', '=', 'cash_out.created_by')
            ->when($companyId, fn ($q) => $q->whereRaw('COALESCE(journal_entries.company_id, users.company_id) = ?', [$companyId]))
            ->when($dateFrom, fn ($q) => $q->whereDate('cash_out.date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('cash_out.date', '<=', $dateTo->toDateString()))
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

        foreach ($posRows as $row) {
            $key = (string) $row['category'];
            $existing = $bucket[$key] ?? [
                'label' => $this->humanizeCategoryLabel((string) $row['category']),
                'income' => 0.0,
                'expense' => 0.0,
            ];

            if ($row['direction'] === 'in') {
                $existing['income'] += (float) $row['amount'];
            } else {
                $existing['expense'] += (float) $row['amount'];
            }

            $bucket[$key] = $existing;
        }

        foreach ($supplierPaymentRows as $row) {
            $key = (string) $row['category'];
            $existing = $bucket[$key] ?? [
                'label' => $this->humanizeCategoryLabel((string) $row['category']),
                'income' => 0.0,
                'expense' => 0.0,
            ];
            $existing['expense'] += (float) $row['amount'];
            $bucket[$key] = $existing;
        }

        foreach ($inventoryRows as $row) {
            $key = (string) $row['category'];
            $existing = $bucket[$key] ?? [
                'label' => $this->humanizeCategoryLabel((string) $row['category']),
                'income' => 0.0,
                'expense' => 0.0,
            ];
            $existing['expense'] += (float) $row['amount'];
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

    /**
     * @return Collection<int, array{
     *     company_id:int|null,
     *     company_name:string,
     *     date:string,
     *     direction:string,
     *     amount:float,
     *     category:string,
     *     cash_account_id:int|null,
     *     cash_account_label:string|null
     * }>
     */
    private function posOverviewRows(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        $sales = PosSale::query()
            ->with('soldBy:id,company_id')
            ->when($dateFrom, fn (Builder $q) => $q->whereDate('sold_at', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn (Builder $q) => $q->whereDate('sold_at', '<=', $dateTo->toDateString()))
            ->orderBy('sold_at')
            ->get(['id', 'number', 'status', 'grand_total', 'sold_at', 'sold_by']);

        if ($sales->isEmpty()) {
            return collect();
        }

        $journalMap = DB::table('journal_entries')
            ->whereIn('source_module', ['pos_sale', 'pos_sale_refund', 'pos_sale_reopen'])
            ->whereIn('source_reference', $sales->pluck('number')->all())
            ->get(['id', 'company_id', 'source_module', 'source_reference'])
            ->keyBy(fn ($row) => $row->source_module.'|'.$row->source_reference);

        $cashLines = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('journal_entry_id', $journalMap->pluck('id')->filter()->values()->all())
            ->where('accounts.is_cash_bank', true)
            ->select('journal_lines.journal_entry_id', 'accounts.id as account_id', 'accounts.code', 'accounts.name')
            ->get()
            ->keyBy('journal_entry_id');

        $companyNames = Company::query()
            ->whereIn('id', $sales->pluck('soldBy.company_id')
                ->merge($journalMap->pluck('company_id'))
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->pluck('name', 'id');

        return $sales
            ->map(function (PosSale $sale) use ($companyId, $journalMap, $cashLines, $companyNames): ?array {
                $status = (string) $sale->status;
                $sourceModule = match ($status) {
                    'refunded' => 'pos_sale_refund',
                    'reopened' => 'pos_sale_reopen',
                    default => 'pos_sale',
                };

                $journal = $journalMap->get($sourceModule.'|'.$sale->number);
                $resolvedCompanyId = $journal?->company_id ? (int) $journal->company_id : ($sale->soldBy?->company_id ? (int) $sale->soldBy->company_id : null);

                if ($companyId && $resolvedCompanyId !== $companyId) {
                    return null;
                }

                $cashLine = $journal?->id ? $cashLines->get($journal->id) : null;

                return [
                    'company_id' => $resolvedCompanyId,
                    'company_name' => $resolvedCompanyId ? (string) ($companyNames[$resolvedCompanyId] ?? 'Belum ditentukan') : 'Belum ditentukan',
                    'date' => $sale->sold_at?->format('Y-m-d') ?? now()->toDateString(),
                    'direction' => $status === 'refunded' ? 'out' : 'in',
                    'amount' => (float) $sale->grand_total,
                    'category' => $status === 'refunded' ? 'refund_penjualan_pos' : 'penjualan_pos',
                    'cash_account_id' => $cashLine?->account_id ? (int) $cashLine->account_id : null,
                    'cash_account_label' => $cashLine ? trim($cashLine->code.' - '.$cashLine->name) : null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     company_id:int|null,
     *     company_name:string,
     *     date:string,
     *     amount:float,
     *     category:string,
     *     cash_account_id:int|null,
     *     cash_account_label:string|null
     * }>
     */
    private function supplierPaymentOverviewRows(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        $payments = PayablePayment::query()
            ->with(['journalEntry:id,company_id', 'cashAccount:id,code,name'])
            ->when($companyId, fn (Builder $q) => $q->whereHas('journalEntry', fn (Builder $journal) => $journal->where('company_id', $companyId)))
            ->when($dateFrom, fn (Builder $q) => $q->whereDate('payment_date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn (Builder $q) => $q->whereDate('payment_date', '<=', $dateTo->toDateString()))
            ->orderBy('payment_date')
            ->get(['id', 'payment_date', 'amount', 'cash_account_id', 'journal_entry_id']);

        if ($payments->isEmpty()) {
            return collect();
        }

        $companyNames = Company::query()
            ->whereIn('id', $payments->pluck('journalEntry.company_id')->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        return $payments->map(function (PayablePayment $payment) use ($companyNames): array {
            $resolvedCompanyId = $payment->journalEntry?->company_id ? (int) $payment->journalEntry->company_id : null;

            return [
                'company_id' => $resolvedCompanyId,
                'company_name' => $resolvedCompanyId ? (string) ($companyNames[$resolvedCompanyId] ?? 'Belum ditentukan') : 'Belum ditentukan',
                'date' => $payment->payment_date?->format('Y-m-d') ?? now()->toDateString(),
                'amount' => (float) $payment->amount,
                'category' => 'pembayaran_hutang_supplier',
                'cash_account_id' => $payment->cash_account_id ? (int) $payment->cash_account_id : null,
                'cash_account_label' => $payment->cashAccount?->displayLabel(),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array{
     *     company_id:int|null,
     *     company_name:string,
     *     date:string,
     *     amount:float,
     *     category:string,
     *     cash_account_id:int|null,
     *     cash_account_label:string|null
     * }>
     */
    private function inventoryOverviewRows(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        $records = AccountingInventoryRecord::query()
            ->with(['journalEntry:id,company_id', 'cashAccount:id,code,name'])
            ->when($companyId, fn (Builder $q) => $q->whereHas('journalEntry', fn (Builder $journal) => $journal->where('company_id', $companyId)))
            ->when($dateFrom, fn (Builder $q) => $q->whereDate('acquisition_date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn (Builder $q) => $q->whereDate('acquisition_date', '<=', $dateTo->toDateString()))
            ->orderBy('acquisition_date')
            ->get(['id', 'acquisition_date', 'amount', 'cash_account_id', 'journal_entry_id']);

        if ($records->isEmpty()) {
            return collect();
        }

        $companyNames = Company::query()
            ->whereIn('id', $records->pluck('journalEntry.company_id')->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        return $records->map(function (AccountingInventoryRecord $record) use ($companyNames): array {
            $resolvedCompanyId = $record->journalEntry?->company_id ? (int) $record->journalEntry->company_id : null;

            return [
                'company_id' => $resolvedCompanyId,
                'company_name' => $resolvedCompanyId ? (string) ($companyNames[$resolvedCompanyId] ?? 'Belum ditentukan') : 'Belum ditentukan',
                'date' => $record->acquisition_date?->format('Y-m-d') ?? now()->toDateString(),
                'amount' => (float) $record->amount,
                'category' => 'pembelian_inventaris',
                'cash_account_id' => $record->cash_account_id ? (int) $record->cash_account_id : null,
                'cash_account_label' => $record->cashAccount?->displayLabel(),
            ];
        })->values();
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
