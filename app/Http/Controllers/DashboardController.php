<?php

namespace App\Http\Controllers;

use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\Project;
use App\Models\TeamDistribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('anggota')) {
            return $this->memberDashboard($user);
        }

        return $this->adminDashboard($request);
    }

    private function adminDashboard(Request $request)
    {
        $year = $request->get('year', now()->year);

        $totalIncome  = CashIn::sum('amount');
        $totalExpense = CashOut::sum('amount');
        $activeCount  = Project::active()->count();
        $netProfit    = $totalIncome - $totalExpense;

        // Monthly chart data for the selected year
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[] = [
                'month'   => $m,
                'income'  => (float) CashIn::whereYear('date', $year)->whereMonth('date', $m)->sum('amount'),
                'expense' => (float) CashOut::whereYear('date', $year)->whereMonth('date', $m)->sum('amount'),
            ];
        }

        $recentProjects = Project::withTrashed(false)
            ->with('payments')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'client_name' => $p->client_name,
                'status'      => $p->status,
                'total_value' => (float) $p->total_value,
                'paid_terms'  => $p->payments->where('paid_at', '!=', null)->count(),
                'total_terms' => $p->payments->count(),
            ]);

        $overduePayments = [];

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'total_income'  => (float) $totalIncome,
                'total_expense' => (float) $totalExpense,
                'net_profit'    => (float) $netProfit,
                'active_count'  => $activeCount,
            ],
            'monthlyData'     => $monthlyData,
            'recentProjects'  => $recentProjects,
            'overduePayments' => $overduePayments,
            'selectedYear'    => (int) $year,
            'years'           => range(now()->year, now()->year - 4),
        ]);
    }

    private function memberDashboard(object $user)
    {
        $distributions = TeamDistribution::with('project')
            ->where('user_id', $user->id)
            ->get();

        $totalEarned = $distributions->sum('total_pay');

        $projectList = $distributions->map(fn ($d) => [
            'project_id'      => $d->project_id,
            'project_name'    => $d->project->name,
            'project_status'  => $d->project->status,
            'role_in_project' => $d->role_in_project,
            'percentage'      => (float) $d->percentage,
            'base_pay'        => (float) $d->base_pay,
            'bonus'           => (float) $d->bonus,
            'total_pay'       => (float) $d->total_pay,
        ]);

        return Inertia::render('Dashboard/Member', [
            'totalEarned' => (float) $totalEarned,
            'projectList' => $projectList,
        ]);
    }
}
