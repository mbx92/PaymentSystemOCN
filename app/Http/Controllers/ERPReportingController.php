<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
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

    public function generalLedger(): Response
    {
        $entries = JournalEntry::query()
            ->with('lines.account')
            ->latest('entry_date')
            ->paginate(25);

        return Inertia::render('ERP/Reports/GeneralLedger', [
            'entries' => $entries,
        ]);
    }

    public function trialBalance(): Response
    {
        $balances = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->select([
                'accounts.code',
                'accounts.name',
                DB::raw('SUM(journal_lines.debit) as debit_total'),
                DB::raw('SUM(journal_lines.credit) as credit_total'),
            ])
            ->orderBy('accounts.code')
            ->get();

        return Inertia::render('ERP/Reports/TrialBalance', [
            'balances' => $balances,
        ]);
    }
}
