<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\CashIn;
use App\Models\CashOut;
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

        $accounts = $query->get();
        $usageById = $this->accountUsageByAccountIds($accounts->pluck('id')->all());

        return Inertia::render('ERP/Accounting/ChartOfAccounts', [
            'accounts' => $accounts->map(function (Account $account) use ($usageById): array {
                $usage = $usageById[$account->id] ?? $this->emptyAccountUsage();

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'normal_balance' => $account->normal_balance,
                    'status' => $account->is_active ? 'active' : 'inactive',
                    ...$usage,
                ];
            }),
            'filters' => $request->only(['q', 'type', 'status']),
            'types' => ['asset', 'liability', 'equity', 'revenue', 'expense'],
        ]);
    }

    public function generalLedger(Request $request): Response
    {
        $companyId = ErpCompanyResolver::resolveForReporting($request);

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

        $entries = $query->latest('entry_date')->latest('id')->paginate($this->resolvedPerPage($request))->withQueryString();

        $filterProps = $this->filtersWithPerPage($request, ['date_from', 'date_to', 'q', 'company_id']);
        if ($companyId && ! $request->filled('company_id')) {
            $filterProps['company_id'] = $companyId;
        }

        $totalsQuery = JournalLine::query()->whereHas('journalEntry', function ($q) use ($request, $companyId): void {
            if ($companyId) {
                $q->where('company_id', $companyId);
            }
            if ($request->filled('date_from')) {
                $q->where('entry_date', '>=', $request->string('date_from')->toString());
            }
            if ($request->filled('date_to')) {
                $q->where('entry_date', '<=', $request->string('date_to')->toString());
            }
        });

        $totals = $totalsQuery->select([
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(credit) as total_credit'),
        ])->first();

        return Inertia::render('ERP/Reports/GeneralLedger', [
            'entries' => $entries,
            'totals' => [
                'total_debit' => (float) ($totals->total_debit ?? 0),
                'total_credit' => (float) ($totals->total_credit ?? 0),
                'entry_count' => $entries->total(),
            ],
            'filters' => $filterProps,
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        $companyId = ErpCompanyResolver::resolveForReporting($request);

        $query = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id');

        if ($companyId) {
            $query->where('journal_entries.company_id', $companyId);
        }

        $this->applyJournalSourceFilter($query, $this->sourceFilter($request));

        $balances = $query
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->select([
                'accounts.code',
                'accounts.name',
                'accounts.type',
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

        $filterProps = $this->filtersWithPerPage($request, ['source', 'company_id']);
        if ($companyId && ! $request->filled('company_id')) {
            $filterProps['company_id'] = $companyId;
        }

        return Inertia::render('ERP/Reports/TrialBalance', [
            'balances' => $paginatedBalances,
            'totals' => [
                'debit' => (float) $totalDebit,
                'credit' => (float) $totalCredit,
                'balanced' => abs($totalDebit - $totalCredit) < 0.01,
            ],
            'filters' => $filterProps,
            'sourceOptions' => $this->sourceOptions(),
        ]);
    }

    private function applyJournalSourceFilter($query, string $source): void
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
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $cashOutIds = CashOut::query()
            ->when($source === 'project', fn ($q) => $q->whereNotNull('project_id'))
            ->when($source === 'manual', fn ($q) => $q->whereNull('project_id'))
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

    public function storeChartOfAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32|unique:accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
            'is_active' => 'nullable|boolean',
        ]);

        Account::query()->create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'normal_balance' => $validated['normal_balance'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
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
        ]);

        $account->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'normal_balance' => $validated['normal_balance'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
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
