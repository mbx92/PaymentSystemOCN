<?php

namespace App\Http\Controllers;

use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\Project;
use App\Models\TeamDistribution;
use App\Services\AccountingCashSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AccountingCashSummaryService $accountingCashSummaryService,
    ) {}

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
        $companyId = ErpCompanyResolver::resolveForReporting($request);
        $cashSummary = $this->accountingCashSummaryService->totals($companyId);
        $activeCount  = Project::active()->count();
        $projectStatus = Project::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $monthlyData = $this->accountingCashSummaryService->monthlyData((int) $year, $companyId);

        $recentProjects = Project::withTrashed(false)
            ->with('payments')
            ->withSum('cashIns as paid_amount', 'amount')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'client_name' => $p->client_name,
                'status'      => $p->status,
                'total_value' => (float) $p->total_value,
                'paid_amount' => (float) ($p->paid_amount ?? 0),
            ]);

        $overduePayments = Project::query()
            ->withSum('cashIns as paid_amount', 'amount')
            ->where('status', 'selesai')
            ->get()
            ->map(function (Project $project) {
                $remaining = max((float) $project->total_value - (float) ($project->paid_amount ?? 0), 0);
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client_name' => $project->client_name,
                    'total_value' => (float) $project->total_value,
                    'paid_amount' => (float) ($project->paid_amount ?? 0),
                    'remaining_amount' => $remaining,
                ];
            })
            ->filter(fn (array $row) => $row['remaining_amount'] > 0)
            ->sortByDesc('remaining_amount')
            ->take(8)
            ->values();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'total_income'  => (float) $cashSummary['cash_in'],
                'total_expense' => (float) $cashSummary['cash_out'],
                'net_cashflow'  => (float) $cashSummary['net_cashflow'],
                'active_count'  => $activeCount,
            ],
            'monthlyData'     => $monthlyData,
            'recentProjects'  => $recentProjects,
            'projectStatusSummary' => [
                'negosiasi' => (int) ($projectStatus['negosiasi'] ?? 0),
                'berjalan' => (int) ($projectStatus['berjalan'] ?? 0),
                'selesai' => (int) ($projectStatus['selesai'] ?? 0),
                'dibatalkan' => (int) ($projectStatus['dibatalkan'] ?? 0),
            ],
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
