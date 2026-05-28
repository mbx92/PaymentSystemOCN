<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\CashIn;
use App\Models\CashOut;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ERPReportingController extends Controller
{
    public function companyRevenue(Request $request): Response
    {
        [$selectedYear, $dateFrom, $dateTo] = $this->resolveReportingDateRange($request);
        $companyId = ErpCompanyResolver::resolveForReporting($request);
        $source = $this->sourceFilter($request);

        $baseQuery = $this->buildRevenueJournalLineQuery($dateFrom, $dateTo, $companyId, $source);

        $rows = (clone $baseQuery)
            ->groupBy('journal_entries.company_id', 'companies.name')
            ->selectRaw('journal_entries.company_id as company_id')
            ->selectRaw("COALESCE(companies.name, 'Belum ditentukan') as company_name")
            ->selectRaw('COUNT(DISTINCT journal_entries.id) as entry_count')
            ->selectRaw('COUNT(DISTINCT journal_lines.account_id) as account_count')
            ->selectRaw('SUM(journal_lines.credit - journal_lines.debit) as revenue_total')
            ->orderByDesc('revenue_total')
            ->get()
            ->map(fn ($row): array => [
                'company_id' => $row->company_id ? (int) $row->company_id : null,
                'company_name' => (string) $row->company_name,
                'entry_count' => (int) $row->entry_count,
                'account_count' => (int) $row->account_count,
                'revenue_total' => (float) $row->revenue_total,
            ])
            ->values();

        $sourcePivot = (clone $baseQuery)
            ->groupBy('journal_entries.company_id', 'companies.name', 'journal_entries.source_module')
            ->selectRaw("COALESCE(companies.name, 'Belum ditentukan') as company_name")
            ->selectRaw('journal_entries.source_module as source_module')
            ->selectRaw('COUNT(DISTINCT journal_entries.id) as entry_count')
            ->selectRaw('SUM(journal_lines.credit - journal_lines.debit) as revenue_total')
            ->orderBy('company_name')
            ->orderByDesc('revenue_total')
            ->get()
            ->map(fn ($row): array => [
                'company_name' => (string) $row->company_name,
                'source_label' => $this->journalSourceLabel((string) $row->source_module),
                'entry_count' => (int) $row->entry_count,
                'revenue_total' => (float) $row->revenue_total,
            ])
            ->values();

        $accountBreakdown = (clone $baseQuery)
            ->groupBy('companies.name', 'accounts.code', 'accounts.name')
            ->selectRaw("COALESCE(companies.name, 'Belum ditentukan') as company_name")
            ->selectRaw('accounts.code as account_code')
            ->selectRaw('accounts.name as account_name')
            ->selectRaw('SUM(journal_lines.credit - journal_lines.debit) as revenue_total')
            ->orderBy('company_name')
            ->orderBy('accounts.code')
            ->get()
            ->map(fn ($row): array => [
                'company_name' => (string) $row->company_name,
                'account_code' => (string) $row->account_code,
                'account_name' => (string) $row->account_name,
                'revenue_total' => (float) $row->revenue_total,
            ])
            ->values();

        return Inertia::render('ERP/Reports/CompanyRevenue', [
            'selected_year' => $selectedYear,
            'filters' => [
                'company_id' => $request->query('company_id', $companyId ?? ErpCompanyResolver::ALL_COMPANIES),
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'source' => $source,
            ],
            'totals' => [
                'revenue' => (float) $rows->sum('revenue_total'),
                'company_count' => $rows->count(),
                'entry_count' => (int) $rows->sum('entry_count'),
                'account_count' => $accountBreakdown
                    ->map(fn (array $row) => $row['company_name'].'|'.$row['account_code'])
                    ->unique()
                    ->count(),
            ],
            'rows' => $rows->all(),
            'source_pivot' => $sourcePivot->all(),
            'account_breakdown' => $accountBreakdown->all(),
            'sourceOptions' => $this->sourceOptions(),
        ]);
    }

    public function profitLossByCompany(Request $request): Response
    {
        [$selectedYear, $dateFrom, $dateTo] = $this->resolveReportingDateRange($request);
        $companyId = ErpCompanyResolver::resolveForReporting($request);
        $source = $this->sourceFilter($request);

        $query = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->leftJoin('companies', 'companies.id', '=', 'journal_entries.company_id')
            ->whereIn('accounts.type', ['revenue', 'expense'])
            ->whereDate('journal_entries.entry_date', '>=', $dateFrom->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $dateTo->toDateString());

        if ($companyId) {
            $query->where('journal_entries.company_id', $companyId);
        }

        $this->applyJournalSourceFilter($query, $source, $dateFrom->toDateString(), $dateTo->toDateString());

        $rows = $query
            ->groupBy('journal_entries.company_id', 'companies.name')
            ->selectRaw('journal_entries.company_id as company_id')
            ->selectRaw("COALESCE(companies.name, 'Belum ditentukan') as company_name")
            ->selectRaw("SUM(CASE WHEN accounts.type = 'revenue' THEN journal_lines.credit - journal_lines.debit ELSE 0 END) as revenue_total")
            ->selectRaw("SUM(CASE WHEN accounts.type = 'expense' THEN journal_lines.debit - journal_lines.credit ELSE 0 END) as expense_total")
            ->selectRaw('COUNT(DISTINCT journal_entries.id) as entry_count')
            ->orderByDesc('revenue_total')
            ->get()
            ->map(fn ($row): array => [
                'company_id' => $row->company_id ? (int) $row->company_id : null,
                'company_name' => (string) $row->company_name,
                'revenue_total' => (float) $row->revenue_total,
                'expense_total' => (float) $row->expense_total,
                'net_profit' => (float) $row->revenue_total - (float) $row->expense_total,
                'entry_count' => (int) $row->entry_count,
            ])
            ->values();

        $typePivotQuery = clone $query;
        $typePivot = $typePivotQuery
            ->groupBy('companies.name', 'accounts.type')
            ->selectRaw("COALESCE(companies.name, 'Belum ditentukan') as company_name")
            ->selectRaw('accounts.type as account_type')
            ->selectRaw("SUM(CASE WHEN accounts.type = 'revenue' THEN journal_lines.credit - journal_lines.debit ELSE journal_lines.debit - journal_lines.credit END) as amount")
            ->orderBy('company_name')
            ->orderBy('account_type')
            ->get()
            ->map(fn ($row): array => [
                'company_name' => (string) $row->company_name,
                'account_type' => (string) $row->account_type,
                'amount' => (float) $row->amount,
            ])
            ->values();

        $accountBreakdownQuery = clone $query;
        $accountBreakdown = $accountBreakdownQuery
            ->groupBy('companies.name', 'accounts.code', 'accounts.name', 'accounts.type')
            ->selectRaw("COALESCE(companies.name, 'Belum ditentukan') as company_name")
            ->selectRaw('accounts.code as account_code')
            ->selectRaw('accounts.name as account_name')
            ->selectRaw('accounts.type as account_type')
            ->selectRaw("SUM(CASE WHEN accounts.type = 'revenue' THEN journal_lines.credit - journal_lines.debit ELSE journal_lines.debit - journal_lines.credit END) as amount")
            ->orderBy('company_name')
            ->orderBy('accounts.type')
            ->orderBy('accounts.code')
            ->get()
            ->map(fn ($row): array => [
                'company_name' => (string) $row->company_name,
                'account_code' => (string) $row->account_code,
                'account_name' => (string) $row->account_name,
                'account_type' => (string) $row->account_type,
                'amount' => (float) $row->amount,
            ])
            ->values();

        return Inertia::render('ERP/Reports/CompanyProfitLoss', [
            'selected_year' => $selectedYear,
            'filters' => [
                'company_id' => $request->query('company_id', $companyId ?? ErpCompanyResolver::ALL_COMPANIES),
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'source' => $source,
            ],
            'totals' => [
                'revenue' => (float) $rows->sum('revenue_total'),
                'expense' => (float) $rows->sum('expense_total'),
                'net_profit' => (float) $rows->sum('net_profit'),
                'company_count' => $rows->count(),
            ],
            'rows' => $rows->all(),
            'type_pivot' => $typePivot->all(),
            'account_breakdown' => $accountBreakdown->all(),
            'sourceOptions' => $this->sourceOptions(),
        ]);
    }

    public function chartOfAccounts(Request $request): Response
    {
        $query = Account::query()->orderBy('code');

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($inner) use ($term): void {
                $inner->where('code', 'like', '%'.$term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $accounts = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString();
        $usageById = $this->accountUsageByAccountIds(collect($accounts->items())->pluck('id')->all());

        return Inertia::render('ERP/Accounting/ChartOfAccounts', [
            'accounts' => $accounts->through(function (Account $account) use ($usageById): array {
                $usage = $usageById[$account->id] ?? $this->emptyAccountUsage();

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'normal_balance' => $account->normal_balance,
                    'status' => $account->is_active ? 'active' : 'inactive',
                    'is_cash_bank' => (bool) $account->is_cash_bank,
                    ...$usage,
                ];
            }),
            'filters' => $this->filtersWithPerPage($request, ['q', 'type', 'status']),
            'types' => ['asset', 'liability', 'equity', 'revenue', 'expense'],
        ]);
    }

    public function generalLedger(Request $request): Response
    {
        $companyId = ErpCompanyResolver::resolveForReporting($request);
        $source = $this->sourceFilter($request);
        $accountId = $request->integer('account_id');

        $query = JournalEntry::query()->with('lines.account');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($request->filled('date_from')) {
            $query->where('entry_date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->where('entry_date', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($inner) use ($term): void {
                $inner->where('entry_no', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%');
            });
        }

        if ($source !== '') {
            $this->applyJournalSourceFilter($query, $source, $request->date_from, $request->date_to);
        }

        if ($accountId > 0) {
            $query->whereHas('lines', fn ($line) => $line->where('account_id', $accountId));
        }

        $entries = $query->latest('entry_date')->latest('id')->paginate($this->resolvedPerPage($request))->withQueryString();

        $filterProps = $this->filtersWithPerPage($request, ['date_from', 'date_to', 'q', 'company_id', 'source', 'account_id']);
        if ($companyId && ! $request->filled('company_id')) {
            $filterProps['company_id'] = $companyId;
        }

        $totalsQuery = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($companyId, fn ($q) => $q->where('journal_entries.company_id', $companyId))
            ->when($request->filled('date_from'), fn ($q) => $q->where('journal_entries.entry_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->where('journal_entries.entry_date', '<=', $request->string('date_to')->toString()))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = $request->string('q')->toString();
                $q->where(function ($inner) use ($term): void {
                    $inner->where('journal_entries.entry_no', 'like', '%'.$term.'%')
                        ->orWhere('journal_entries.description', 'like', '%'.$term.'%');
                });
            });

        if ($source !== '') {
            $this->applyJournalSourceFilter($totalsQuery, $source, $request->date_from, $request->date_to);
        }

        if ($accountId > 0) {
            $totalsQuery->where('account_id', $accountId);
        }

        $totals = $totalsQuery->select([
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(credit) as total_credit'),
            DB::raw('COUNT(*) as line_count'),
            DB::raw('COUNT(DISTINCT account_id) as account_count'),
        ])->first();

        $sourcePivotQuery = JournalEntry::query()
            ->select('source_module')
            ->selectRaw('COUNT(*) as entry_count')
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->join('journal_lines', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->when($companyId, fn ($q) => $q->where('journal_entries.company_id', $companyId))
            ->when($request->filled('date_from'), fn ($q) => $q->where('journal_entries.entry_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->where('journal_entries.entry_date', '<=', $request->string('date_to')->toString()))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = $request->string('q')->toString();
                $q->where(function ($inner) use ($term): void {
                    $inner->where('journal_entries.entry_no', 'like', '%'.$term.'%')
                        ->orWhere('journal_entries.description', 'like', '%'.$term.'%');
                });
            })
            ->when($accountId > 0, fn ($q) => $q->where('journal_lines.account_id', $accountId));

        if ($source !== '') {
            $this->applyJournalSourceFilter($sourcePivotQuery, $source, $request->date_from, $request->date_to);
        }

        $sourcePivot = $sourcePivotQuery
            ->groupBy('source_module')
            ->orderBy('source_module')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->journalSourceLabel((string) $row->source_module),
                'entry_count' => (int) $row->entry_count,
                'total_debit' => (float) $row->total_debit,
                'total_credit' => (float) $row->total_credit,
                'balance' => (float) $row->total_debit - (float) $row->total_credit,
            ])
            ->values();

        $accountPivot = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->select('accounts.id', 'accounts.code', 'accounts.name')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->when($companyId, fn ($q) => $q->where('journal_entries.company_id', $companyId))
            ->when($request->filled('date_from'), fn ($q) => $q->where('journal_entries.entry_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->where('journal_entries.entry_date', '<=', $request->string('date_to')->toString()))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = $request->string('q')->toString();
                $q->where(function ($inner) use ($term): void {
                    $inner->where('journal_entries.entry_no', 'like', '%'.$term.'%')
                        ->orWhere('journal_entries.description', 'like', '%'.$term.'%');
                });
            });

        if ($source !== '') {
            $this->applyJournalSourceFilter($accountPivot, $source, $request->date_from, $request->date_to);
        }

        if ($accountId > 0) {
            $accountPivot->where('journal_lines.account_id', $accountId);
        }

        $accountPivot = $accountPivot
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'line_count' => (int) $row->line_count,
                'total_debit' => (float) $row->total_debit,
                'total_credit' => (float) $row->total_credit,
                'balance' => (float) $row->total_debit - (float) $row->total_credit,
            ])
            ->values();

        return Inertia::render('ERP/Reports/GeneralLedger', [
            'entries' => $entries,
            'totals' => [
                'total_debit' => (float) ($totals->total_debit ?? 0),
                'total_credit' => (float) ($totals->total_credit ?? 0),
                'entry_count' => $entries->total(),
                'line_count' => (int) ($totals->line_count ?? 0),
                'account_count' => (int) ($totals->account_count ?? 0),
            ],
            'filters' => $filterProps,
            'sourceOptions' => $this->sourceOptions(),
            'accountOptions' => Account::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Account $account) => [
                    'value' => $account->id,
                    'label' => $account->code.' - '.$account->name,
                ]),
            'pivot' => [
                'sources' => $sourcePivot,
                'accounts' => $accountPivot,
            ],
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        $companyId = ErpCompanyResolver::resolveForReporting($request);
        $source = $this->sourceFilter($request);
        $type = $request->string('type')->toString();
        $type = in_array($type, ['asset', 'liability', 'equity', 'revenue', 'expense'], true) ? $type : '';
        $term = $request->string('q')->toString();

        $query = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id');

        if ($companyId) {
            $query->where('journal_entries.company_id', $companyId);
        }

        if ($request->filled('date_from')) {
            $query->where('journal_entries.entry_date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->where('journal_entries.entry_date', '<=', $request->string('date_to')->toString());
        }

        if ($type !== '') {
            $query->where('accounts.type', $type);
        }

        if ($term !== '') {
            $query->where(function ($inner) use ($term): void {
                $inner->where('accounts.code', 'like', '%'.$term.'%')
                    ->orWhere('accounts.name', 'like', '%'.$term.'%')
                    ->orWhere('journal_entries.entry_no', 'like', '%'.$term.'%')
                    ->orWhere('journal_entries.description', 'like', '%'.$term.'%');
            });
        }

        $this->applyJournalSourceFilter($query, $source, $request->date_from, $request->date_to);

        $balances = $query
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->select([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('COUNT(journal_lines.account_id) as line_count'),
                DB::raw('SUM(journal_lines.debit) as debit_total'),
                DB::raw('SUM(journal_lines.credit) as credit_total'),
            ])
            ->orderBy('accounts.code')
            ->get();

        $totalDebit = $balances->sum('debit_total');
        $totalCredit = $balances->sum('credit_total');

        $perPage = $this->resolvedPerPage($request);
        $currentPage = Paginator::resolveCurrentPage();
        $paginatedBalances = new LengthAwarePaginator(
            $balances->forPage($currentPage, $perPage)->values(),
            $balances->count(),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()],
        );
        $paginatedBalances->withQueryString();

        $filterProps = $this->filtersWithPerPage($request, ['source', 'company_id', 'date_from', 'date_to', 'type', 'q']);
        if ($companyId && ! $request->filled('company_id')) {
            $filterProps['company_id'] = $companyId;
        }

        $typePivot = $balances
            ->groupBy('type')
            ->map(function ($rows, $accountType): array {
                return [
                    'type' => $accountType,
                    'account_count' => $rows->count(),
                    'total_debit' => (float) $rows->sum('debit_total'),
                    'total_credit' => (float) $rows->sum('credit_total'),
                    'balance' => (float) $rows->sum(fn ($row) => (float) $row->debit_total - (float) $row->credit_total),
                ];
            })
            ->values();

        return Inertia::render('ERP/Reports/TrialBalance', [
            'balances' => $paginatedBalances,
            'totals' => [
                'debit' => (float) $totalDebit,
                'credit' => (float) $totalCredit,
                'balanced' => abs($totalDebit - $totalCredit) < 0.01,
                'account_count' => $balances->count(),
                'line_count' => (int) $balances->sum('line_count'),
            ],
            'filters' => $filterProps,
            'sourceOptions' => $this->sourceOptions(),
            'typeOptions' => [
                ['value' => '', 'label' => 'Semua Tipe'],
                ['value' => 'asset', 'label' => 'Aset'],
                ['value' => 'liability', 'label' => 'Liabilitas'],
                ['value' => 'equity', 'label' => 'Ekuitas'],
                ['value' => 'revenue', 'label' => 'Pendapatan'],
                ['value' => 'expense', 'label' => 'Beban'],
            ],
            'pivot' => [
                'types' => $typePivot,
            ],
        ]);
    }

    private function buildRevenueJournalLineQuery(Carbon $dateFrom, Carbon $dateTo, int|string|null $companyId, string $source): Builder
    {
        $query = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->leftJoin('companies', 'companies.id', '=', 'journal_entries.company_id')
            ->where('accounts.type', 'revenue')
            ->whereDate('journal_entries.entry_date', '>=', $dateFrom->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $dateTo->toDateString());

        if ($companyId) {
            $query->where('journal_entries.company_id', $companyId);
        }

        $this->applyJournalSourceFilter($query, $source, $dateFrom->toDateString(), $dateTo->toDateString());

        return $query;
    }

    private function applyJournalSourceFilter($query, string $source, ?string $dateFrom = null, ?string $dateTo = null): void
    {
        if ($source === '') {
            return;
        }

        $posModules = ['pos_sale', 'pos_sale_refund', 'pos_sale_reopen'];

        if ($source === 'pos') {
            $query->whereIn('journal_entries.source_module', $posModules);

            return;
        }

        if ($source === 'opening_balance') {
            $query->where('journal_entries.source_module', 'opening_balance');

            return;
        }

        $cashInIds = CashIn::query()
            ->when($source === 'project', fn ($q) => $q->whereNotNull('project_id'))
            ->when($source === 'manual', fn ($q) => $q->whereNull('project_id'))
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $cashOutIds = CashOut::query()
            ->when($source === 'project', fn ($q) => $q->whereNotNull('project_id'))
            ->when($source === 'manual', fn ($q) => $q->whereNull('project_id'))
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $query->where(function ($inner) use ($source, $cashInIds, $cashOutIds): void {
            if ($source === 'project') {
                $inner->where('journal_entries.source_module', 'project_invoice_payment');
            } else {
                $inner->whereRaw('1 = 0');
            }

            if ($cashInIds !== []) {
                $inner->orWhere(function ($nested) use ($cashInIds): void {
                    $nested
                        ->where('journal_entries.source_module', 'cash_in')
                        ->whereIn('journal_entries.source_reference', $cashInIds);
                });
            }

            if ($cashOutIds !== []) {
                $inner->orWhere(function ($nested) use ($cashOutIds): void {
                    $nested
                        ->whereIn('journal_entries.source_module', ['cash_out', 'operational_cash_out'])
                        ->whereIn('journal_entries.source_reference', $cashOutIds);
                });
            }
        });
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function sourceOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Semua Sumber'],
            ['value' => 'project', 'label' => 'Project'],
            ['value' => 'pos', 'label' => 'POS'],
            ['value' => 'manual', 'label' => 'Manual / Umum'],
            ['value' => 'opening_balance', 'label' => 'Saldo Awal'],
        ];
    }

    private function sourceFilter(Request $request): string
    {
        $source = $request->string('source')->toString();

        return in_array($source, ['project', 'pos', 'manual', 'opening_balance'], true) ? $source : '';
    }

    /**
     * @return array{0:int,1:Carbon,2:Carbon}
     */
    private function resolveReportingDateRange(Request $request): array
    {
        $selectedYear = (int) $request->integer('year', now()->year);
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->string('date_from')->toString())->startOfDay()
            : Carbon::create($selectedYear, 1, 1)->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->string('date_to')->toString())->endOfDay()
            : Carbon::create($selectedYear, 12, 31)->endOfDay();

        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$selectedYear, $dateFrom, $dateTo];
    }

    private function journalSourceLabel(string $sourceModule): string
    {
        return match ($sourceModule) {
            'project_invoice_payment' => 'Project Invoice Payment',
            'cash_in' => 'Cash In',
            'cash_out' => 'Cash Out',
            'operational_cash_out' => 'Operational Cash Out',
            'pos_sale', 'pos_sale_refund', 'pos_sale_reopen' => 'POS',
            'opening_balance' => 'Opening Balance',
            default => str($sourceModule)->replace('_', ' ')->title()->toString(),
        };
    }

    public function storeChartOfAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32|unique:accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
            'is_active' => 'nullable|boolean',
            'is_cash_bank' => 'nullable|boolean',
        ]);

        Account::query()->create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'normal_balance' => $validated['normal_balance'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_cash_bank' => $validated['type'] === 'asset' && (bool) ($validated['is_cash_bank'] ?? false),
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Akun CoA berhasil ditambahkan.']);
    }

    public function updateChartOfAccount(Request $request, Account $account): RedirectResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('accounts', 'code')->ignore($account->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'is_active' => ['nullable', 'boolean'],
            'is_cash_bank' => ['nullable', 'boolean'],
        ]);

        $account->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'normal_balance' => $validated['normal_balance'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_cash_bank' => $validated['type'] === 'asset' && (bool) ($validated['is_cash_bank'] ?? false),
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Akun CoA berhasil diperbarui.']);
    }

    public function destroyChartOfAccount(Account $account): RedirectResponse
    {
        $usage = $this->accountUsageByAccountIds([$account->id])[$account->id] ?? $this->emptyAccountUsage();

        if (! $usage['can_delete']) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Akun tidak dapat dihapus: '.$usage['delete_blocked_summary'],
            ]);
        }

        $account->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Akun CoA berhasil dihapus.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAccountUsage(): array
    {
        return [
            'journal_line_count' => 0,
            'total_debit' => 0.0,
            'total_credit' => 0.0,
            'category_mapping_count' => 0,
            'cash_in_count' => 0,
            'cash_out_count' => 0,
            'payable_payment_count' => 0,
            'has_posting_value' => false,
            'can_delete' => true,
            'delete_blocked_summary' => null,
        ];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function accountUsageByAccountIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = $this->emptyAccountUsage();
        }

        foreach (JournalLine::query()
            ->whereIn('account_id', $ids)
            ->select('account_id')
            ->selectRaw('COUNT(*) as c')
            ->selectRaw('COALESCE(SUM(debit), 0) as td')
            ->selectRaw('COALESCE(SUM(credit), 0) as tc')
            ->groupBy('account_id')
            ->get() as $row) {
            $id = (int) $row->account_id;
            $out[$id]['journal_line_count'] = (int) $row->c;
            $out[$id]['total_debit'] = (float) $row->td;
            $out[$id]['total_credit'] = (float) $row->tc;
            $out[$id]['has_posting_value'] = (int) $row->c > 0;
        }

        foreach (DB::table('category_coa_mappings')->whereIn('account_id', $ids)->select('account_id', DB::raw('COUNT(*) as c'))->groupBy('account_id')->get() as $row) {
            $out[(int) $row->account_id]['category_mapping_count'] = (int) $row->c;
        }

        foreach (DB::table('cash_in')->whereIn('cash_account_id', $ids)->select('cash_account_id', DB::raw('COUNT(*) as c'))->groupBy('cash_account_id')->get() as $row) {
            $out[(int) $row->cash_account_id]['cash_in_count'] = (int) $row->c;
        }

        foreach (DB::table('cash_out')->whereIn('cash_account_id', $ids)->select('cash_account_id', DB::raw('COUNT(*) as c'))->groupBy('cash_account_id')->get() as $row) {
            $out[(int) $row->cash_account_id]['cash_out_count'] = (int) $row->c;
        }

        foreach (PayablePayment::query()
            ->whereIn('cash_account_id', $ids)
            ->select('cash_account_id')
            ->selectRaw('COUNT(*) as c')
            ->groupBy('cash_account_id')
            ->get() as $row) {
            $out[(int) $row->cash_account_id]['payable_payment_count'] = (int) $row->c;
        }

        foreach ($ids as $id) {
            $u = $out[$id];
            $blocked = [];
            if ($u['journal_line_count'] > 0) {
                $blocked[] = 'ada '.$u['journal_line_count'].' baris jurnal (total debit '.number_format($u['total_debit'], 2, ',', '.').', kredit '.number_format($u['total_credit'], 2, ',', '.').')';
            }
            if ($u['category_mapping_count'] > 0) {
                $blocked[] = 'dipakai di '.$u['category_mapping_count'].' mapping kategori kas';
            }
            if ($u['cash_in_count'] > 0) {
                $blocked[] = 'terhubung ke '.$u['cash_in_count'].' kas masuk';
            }
            if ($u['cash_out_count'] > 0) {
                $blocked[] = 'terhubung ke '.$u['cash_out_count'].' kas keluar';
            }
            if ($u['payable_payment_count'] > 0) {
                $blocked[] = 'terhubung ke '.$u['payable_payment_count'].' pembayaran hutang';
            }

            $out[$id]['can_delete'] = $blocked === [];
            $out[$id]['delete_blocked_summary'] = $blocked === [] ? null : implode('; ', $blocked);
        }

        return $out;
    }
}
