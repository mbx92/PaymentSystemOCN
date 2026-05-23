<?php

namespace App\Http\Controllers;

use App\ERP\Core\Models\Company;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\Project;
use App\Services\CashflowReportService;
use App\Services\PosReportService;
use App\Services\ProjectReportService;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\PaymentMethod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private readonly CashflowReportService $cashflowReportService,
        private readonly ProjectReportService $projectReportService,
        private readonly PosReportService $posReportService,
    ) {
    }

    public function cashflow(Request $request)
    {
        $report = $this->cashflowReportService->build($request);

        return Inertia::render('Reports/Cashflow', [
            'summary' => $report['summary'],
            'pivot' => $report['pivot'],
            'transactions' => $report['transactions'],
            'filters' => $report['filters'],
            'companyOptions' => Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Company $company) => [
                    'value' => (string) $company->id,
                    'label' => $company->name,
                ])
                ->values()
                ->all(),
            'projectOptions' => Project::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Project $project) => [
                    'value' => $project->id,
                    'label' => $project->name,
                ])
                ->all(),
            'sourceOptions' => [
                ['value' => 'all', 'label' => 'Semua Sumber'],
                ['value' => 'project', 'label' => 'Project'],
                ['value' => 'manual', 'label' => 'Manual / Umum'],
                ['value' => 'pos', 'label' => 'POS'],
                ['value' => 'supplier_payment', 'label' => 'Pembayaran Supplier'],
            ],
            'groupByOptions' => [
                ['value' => 'day', 'label' => 'Harian'],
                ['value' => 'week', 'label' => 'Mingguan'],
                ['value' => 'month', 'label' => 'Bulanan'],
            ],
            'filtersMeta' => $this->filtersWithPerPage($request, ['date_from', 'date_to', 'source', 'project_id', 'group_by', 'company_id']),
        ]);
    }

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

    public function projects(Request $request)
    {
        $report = $this->projectReportService->build($request);

        return Inertia::render('Reports/Projects', [
            'summary' => $report['summary'],
            'pivot' => $report['pivot'],
            'projects' => $report['projects'],
            'filters' => $report['filters'],
            'statusOptions' => [
                ['value' => '', 'label' => 'Semua Status'],
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'berjalan', 'label' => 'Berjalan'],
                ['value' => 'selesai', 'label' => 'Selesai'],
                ['value' => 'batal', 'label' => 'Batal'],
            ],
            'projectTypeOptions' => Project::query()
                ->select('project_type')
                ->whereNotNull('project_type')
                ->distinct()
                ->orderBy('project_type')
                ->get()
                ->map(fn (Project $project) => [
                    'value' => (string) $project->project_type,
                    'label' => (string) $project->project_type,
                ])
                ->values()
                ->all(),
            'filtersMeta' => $this->filtersWithPerPage($request, ['status', 'project_type', 'date_from', 'date_to', 'q']),
        ]);
    }

    public function pos(Request $request)
    {
        $report = $this->posReportService->build($request);

        return Inertia::render('Reports/Pos', [
            'summary' => $report['summary'],
            'pivot' => $report['pivot'],
            'transactions' => $report['transactions'],
            'filters' => $report['filters'],
            'statusOptions' => [
                ['value' => '', 'label' => 'Semua Status'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'refunded', 'label' => 'Refunded'],
                ['value' => 'reopened', 'label' => 'Reopened'],
            ],
            'channelOptions' => [
                ['value' => '', 'label' => 'Semua Channel'],
                ['value' => 'retail', 'label' => 'Retail'],
                ['value' => 'grosir', 'label' => 'Grosir'],
                ['value' => 'marketplace', 'label' => 'Marketplace'],
                ['value' => 'project', 'label' => 'Project'],
            ],
            'paymentMethodOptions' => PaymentMethod::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (PaymentMethod $paymentMethod) => [
                    'value' => (string) $paymentMethod->id,
                    'label' => $paymentMethod->name,
                ])
                ->values()
                ->all(),
            'filtersMeta' => $this->filtersWithPerPage($request, ['status', 'channel', 'payment_method_id', 'date_from', 'date_to', 'q']),
        ]);
    }

    public function monthly(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);
        $companyId = ErpCompanyResolver::resolveForReporting($request);

        $cashInsQuery = CashIn::with(['project', 'creator'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->when($companyId, fn ($q) => $q->whereHas('creator', fn ($u) => $u->where('company_id', $companyId)));

        $cashOutsQuery = CashOut::with(['project', 'creator'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->when($companyId, fn ($q) => $q->whereHas('creator', fn ($u) => $u->where('company_id', $companyId)));

        $cashIns = $cashInsQuery->get();
        $cashOuts = $cashOutsQuery->get();

        $totalIn  = (float) $cashIns->sum('amount');
        $totalOut = (float) $cashOuts->sum('amount');

        // Breakdown per category
        $expenseByCategory = $cashOuts->groupBy('category')
            ->map(fn ($items) => (float) $items->sum('amount'))
            ->toArray();
        $incomeByCategory = $cashIns->groupBy('category')
            ->map(fn ($items) => (float) $items->sum('amount'))
            ->toArray();

        $projectBreakdown = $cashIns
            ->groupBy(fn ($row) => $row->project?->name ?? 'Manual / Umum')
            ->map(function ($items, $label) use ($cashOuts): array {
                $projectId = $items->first()?->project_id;
                $relatedOut = $projectId ? $cashOuts->where('project_id', $projectId) : collect();

                return [
                    'label' => $label,
                    'cash_in' => (float) $items->sum('amount'),
                    'cash_out' => (float) $relatedOut->sum('amount'),
                    'net' => (float) $items->sum('amount') - (float) $relatedOut->sum('amount'),
                ];
            })
            ->sortByDesc('cash_in')
            ->values()
            ->take(8)
            ->all();

        $expenseBreakdown = collect($expenseByCategory)
            ->map(fn ($amount, $category) => [
                'label' => str($category)->replace('_', ' ')->title()->toString(),
                'amount' => (float) $amount,
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();

        $daysInMonth = now()->setYear((int) $year)->setMonth((int) $month)->startOfMonth()->daysInMonth;
        $incomeByDay = $cashIns->groupBy(fn ($row) => (int) $row->date->format('d'))
            ->map(fn ($items) => (float) $items->sum('amount'));
        $expenseByDay = $cashOuts->groupBy(fn ($row) => (int) $row->date->format('d'))
            ->map(fn ($items) => (float) $items->sum('amount'));
        $trendData = collect(range(1, $daysInMonth))->map(function (int $day) use ($incomeByDay, $expenseByDay): array {
            $income = (float) ($incomeByDay[$day] ?? 0);
            $expense = (float) ($expenseByDay[$day] ?? 0);

            return [
                'label' => str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
            ];
        })->all();

        return Inertia::render('Reports/Monthly', [
            'totalIn'            => $totalIn,
            'totalOut'           => $totalOut,
            'netProfit'          => $totalIn - $totalOut,
            'expenseByCategory'  => $expenseByCategory,
            'incomeByCategory'   => $incomeByCategory,
            'projectBreakdown'   => $projectBreakdown,
            'expenseBreakdown'   => $expenseBreakdown,
            'trendData'          => $trendData,
            'cashIns' => $cashInsQuery
                ->latest('date')
                ->paginate($this->resolvedPerPage($request), ['*'], 'cash_in_page')
                ->withQueryString()
                ->through(fn ($c) => [
                    'project_name' => $c->project?->name ?? 'Manual / Umum',
                    'category'     => $c->category,
                    'amount'       => (float) $c->amount,
                    'date'         => $c->date->format('Y-m-d'),
                    'note'         => $c->note,
                ]),
            'cashOuts' => $cashOutsQuery
                ->latest('date')
                ->paginate($this->resolvedPerPage($request), ['*'], 'cash_out_page')
                ->withQueryString()
                ->through(fn ($c) => [
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
            'filters'       => $this->filtersWithPerPage($request, ['month', 'year', 'company_id']),
            'companyOptions' => Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Company $company) => [
                    'value' => (string) $company->id,
                    'label' => $company->name,
                ])
                ->values()
                ->all(),
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
