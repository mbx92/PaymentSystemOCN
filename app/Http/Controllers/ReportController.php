<?php

namespace App\Http\Controllers;

use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function projectProfit(Request $request)
    {
        $projects = Project::with(['cashIns', 'cashOuts', 'referrals'])
            ->whereIn('status', ['berjalan', 'selesai'])
            ->when($request->search, fn ($q) => $q->where('name', 'ilike', "%{$request->search}%"))
            ->get()
            ->map(function ($p) {
                $cashIn      = (float) $p->cashIns->sum('amount');
                $cashOut     = (float) $p->cashOuts->sum('amount');
                $profit      = $cashIn - $cashOut;
                $margin      = $cashIn > 0 ? round($profit / $cashIn * 100, 1) : 0;
                $referral    = (float) $p->referrals->sum('commission_amount');
                $operational = (float) $p->cashOuts->where('category', 'operasional')->sum('amount');
                $teamCost    = (float) $p->cashOuts->where('category', 'biaya_tim')->sum('amount');

                return [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'client_name' => $p->client_name,
                    'status'      => $p->status,
                    'total_value' => (float) $p->total_value,
                    'cash_in'     => $cashIn,
                    'referral'    => $referral,
                    'team_cost'   => $teamCost,
                    'operational' => $operational,
                    'cash_out'    => $cashOut,
                    'profit'      => $profit,
                    'margin'      => $margin,
                ];
            });

        return Inertia::render('Reports/ProjectProfit', [
            'projects' => $projects,
            'filters'  => $request->only(['search']),
        ]);
    }

    public function monthly(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $cashIns  = CashIn::with('project')->whereYear('date', $year)->whereMonth('date', $month)->get();
        $cashOuts = CashOut::with('project')->whereYear('date', $year)->whereMonth('date', $month)->get();

        $totalIn  = (float) $cashIns->sum('amount');
        $totalOut = (float) $cashOuts->sum('amount');

        // Breakdown per category
        $expenseByCategory = $cashOuts->groupBy('category')
            ->map(fn ($items) => (float) $items->sum('amount'))
            ->toArray();

        return Inertia::render('Reports/Monthly', [
            'totalIn'            => $totalIn,
            'totalOut'           => $totalOut,
            'netProfit'          => $totalIn - $totalOut,
            'expenseByCategory'  => $expenseByCategory,
            'cashIns'            => $cashIns->map(fn ($c) => [
                'project_name' => $c->project?->name ?? 'Manual / Umum',
                'category'     => $c->category,
                'amount'       => (float) $c->amount,
                'date'         => $c->date->format('Y-m-d'),
                'note'         => $c->note,
            ]),
            'cashOuts'           => $cashOuts->map(fn ($c) => [
                'project_name'   => $c->project?->name ?? 'Operasional Umum',
                'category'       => $c->category,
                'amount'         => (float) $c->amount,
                'date'           => $c->date->format('Y-m-d'),
                'note'           => $c->note,
                'recipient_name' => $c->recipient_name,
            ]),
            'selectedMonth' => (int) $month,
            'selectedYear'  => (int) $year,
            'years'         => range(now()->year, now()->year - 4),
        ]);
    }

    public function exportProjectProfitExcel(Request $request)
    {
        return Excel::download(
            new \App\Exports\ProjectProfitExport($request->only(['search'])),
            'laporan-project-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function exportMonthlyExcel(Request $request)
    {
        return Excel::download(
            new \App\Exports\MonthlyReportExport($request->get('month', now()->month), $request->get('year', now()->year)),
            'laporan-bulanan-' . $request->get('year', now()->year) . '-' . str_pad($request->get('month', now()->month), 2, '0', STR_PAD_LEFT) . '.xlsx'
        );
    }

    public function exportMemberPaymentsExcel(Request $request)
    {
        return Excel::download(
            new \App\Exports\MemberPaymentsExport($request->only(['user_id', 'year'])),
            'laporan-anggota-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
