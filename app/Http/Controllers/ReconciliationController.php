<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\Models\CashIn;
use App\Models\CashOut;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $request->string('period')->toString() ?: 'daily';
        $periodSql = $period === 'weekly' ? "to_char(date_trunc('week', date), 'IYYY-\"W\"IW')" : "to_char(date, 'YYYY-MM-DD')";

        $cashInRows = CashIn::query()
            ->selectRaw("{$periodSql} as bucket, cash_account_id, sum(amount) as total_in")
            ->groupByRaw("bucket, cash_account_id")
            ->get();

        $cashOutRows = CashOut::query()
            ->selectRaw("{$periodSql} as bucket, cash_account_id, sum(amount) as total_out")
            ->groupByRaw("bucket, cash_account_id")
            ->get();

        $accounts = Account::query()->pluck('name', 'id');
        $map = [];
        foreach ($cashInRows as $row) {
            $key = $row->bucket.'|'.$row->cash_account_id;
            $map[$key] = $map[$key] ?? ['bucket' => $row->bucket, 'cash_account_id' => $row->cash_account_id, 'cash_in' => 0.0, 'cash_out' => 0.0];
            $map[$key]['cash_in'] = (float) $row->total_in;
        }
        foreach ($cashOutRows as $row) {
            $key = $row->bucket.'|'.$row->cash_account_id;
            $map[$key] = $map[$key] ?? ['bucket' => $row->bucket, 'cash_account_id' => $row->cash_account_id, 'cash_in' => 0.0, 'cash_out' => 0.0];
            $map[$key]['cash_out'] = (float) $row->total_out;
        }

        $rows = collect($map)
            ->map(fn ($r) => [
                'bucket' => $r['bucket'],
                'cash_account_id' => $r['cash_account_id'],
                'cash_account_name' => $accounts[$r['cash_account_id']] ?? 'Kas/Bank Tidak Dikenal',
                'cash_in' => $r['cash_in'],
                'cash_out' => $r['cash_out'],
                'net' => $r['cash_in'] - $r['cash_out'],
            ])
            ->sortByDesc(fn ($r) => $r['bucket'])
            ->values();

        return Inertia::render('ERP/Accounting/Reconciliation', [
            'period' => $period,
            'rows' => $rows,
        ]);
    }
}

