<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
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

        $entries = $query->latest('entry_date')->latest('id')->paginate(25)->withQueryString();

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
            'filters' => $request->only(['date_from', 'date_to', 'q']),
        ]);
    }

    public function trialBalance(): Response
    {
        $balances = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
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

        return Inertia::render('ERP/Reports/TrialBalance', [
            'balances' => $balances,
            'totals' => [
                'debit' => (float) $totalDebit,
                'credit' => (float) $totalCredit,
                'balanced' => abs($totalDebit - $totalCredit) < 0.01,
            ],
        ]);
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
