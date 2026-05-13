<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\Models\CashIn;
use App\Models\CashOut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('ERP/Accounting/ChartOfAccounts', [
            'accounts' => $query->get()->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $account->normal_balance,
                'status' => $account->is_active ? 'active' : 'inactive',
            ]),
            'filters' => $request->only(['q', 'type', 'status']),
            'types' => ['asset', 'liability', 'equity', 'revenue', 'expense'],
        ]);
    }

    public function generalLedger(Request $request): Response
    {
        $query = JournalEntry::query()->with('lines.account');

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

        $totalsQuery = JournalLine::query();
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $totalsQuery->whereHas('journalEntry', function ($q) use ($request): void {
                if ($request->filled('date_from')) {
                    $q->where('entry_date', '>=', $request->string('date_from')->toString());
                }
                if ($request->filled('date_to')) {
                    $q->where('entry_date', '<=', $request->string('date_to')->toString());
                }
            });
        }

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
            'filters' => $this->filtersWithPerPage($request, ['date_from', 'date_to', 'q']),
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        $query = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id');

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

        return Inertia::render('ERP/Reports/TrialBalance', [
            'balances' => $paginatedBalances,
            'totals' => [
                'debit' => (float) $totalDebit,
                'credit' => (float) $totalCredit,
                'balanced' => abs($totalDebit - $totalCredit) < 0.01,
            ],
            'filters' => $this->filtersWithPerPage($request, ['source']),
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
        ];
    }

    private function sourceFilter(Request $request): string
    {
        $source = $request->string('source')->toString();

        return in_array($source, ['project', 'pos', 'manual'], true) ? $source : '';
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
}
