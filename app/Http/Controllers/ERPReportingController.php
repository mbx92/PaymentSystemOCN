<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ERPReportingController extends Controller
{
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
