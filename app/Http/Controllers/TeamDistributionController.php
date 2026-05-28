<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TeamDistribution;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamDistributionController extends Controller
{
    public function calculator(Request $request): Response|RedirectResponse
    {
        $projectId = $request->query('project_id');
        $search = trim((string) $request->string('q'));
        $statusFilter = trim((string) $request->string('status'));

        if (filled($projectId) && ! Str::isUuid($projectId)) {
            return redirect()->route('team-distribution.calculator');
        }

        $projectQuery = Project::query()
            ->with(['cashIns', 'referrals', 'cashOuts', 'materials.product', 'convertedBudget.items', 'teamDistributions'])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('client_name', 'like', '%'.$search.'%');
                });
            })
            ->when($statusFilter !== '', fn ($builder) => $builder->where('status', $statusFilter))
            ->latest('started_at')
            ->latest('created_at');

        $projectPaginator = $projectQuery
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString();

        $members = User::role('anggota')->orWhereHas('roles', fn ($q) => $q->where('name', 'manajer'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $teamRoles = $this->activeTeamRoles();

        $projectRows = new LengthAwarePaginator(
            $projectPaginator->getCollection()->map(fn (Project $project) => $this->projectDistributionSnapshot($project)),
            $projectPaginator->total(),
            $projectPaginator->perPage(),
            $projectPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $selectedProject = null;
        $existingDistributions = [];

        if (filled($projectId)) {
            $project = Project::query()
                ->with(['cashIns', 'referrals', 'cashOuts', 'materials.product', 'convertedBudget.items', 'teamDistributions'])
                ->find($projectId);

            if (! $project instanceof Project) {
                abort(404);
            }

            $selectedProject = $this->projectDistributionSnapshot($project);

            $existingDistributions = TeamDistribution::with('user')
                ->where('project_id', $projectId)
                ->get()
                ->map(fn ($d) => [
                    'id'              => $d->id,
                    'user_id'         => $d->user_id,
                    'user_name'       => $d->user->name,
                    'role_in_project' => $d->role_in_project,
                    'percentage'      => (float) $d->percentage,
                    'base_pay'        => (float) $d->base_pay,
                    'bonus'           => (float) $d->bonus,
                    'total_pay'       => (float) $d->total_pay,
                ])
                ->values();
        }

        return Inertia::render('TeamDistribution/Calculator', [
            'projects'              => $projectRows,
            'members'               => $members,
            'teamRoles'             => $teamRoles,
            'selectedProject'       => $selectedProject,
            'existingDistributions' => $existingDistributions,
            'selectedProjectId'     => filled($projectId) ? $projectId : null,
            'filters'               => $this->filtersWithPerPage($request, ['q', 'status']),
        ]);
    }

    public function save(Request $request)
    {
        $roleNames = $this->activeTeamRoles()->pluck('name')->all();

        $validated = $request->validate([
            'project_id'    => 'required|uuid|exists:projects,id',
            'distribution_rate' => 'required|numeric|min:0|max:100',
            'distributions' => 'required|array|min:1',
            'distributions.*.user_id'         => 'required|exists:users,id',
            'distributions.*.role_in_project' => ['required', 'string', 'max:20', Rule::in($roleNames)],
            'distributions.*.percentage'      => 'required|numeric|min:0|max:100',
            'distributions.*.base_pay'        => 'required|numeric|min:0',
            'distributions.*.bonus'           => 'required|numeric|min:0',
        ]);

        $totalPercentage = collect($validated['distributions'])->sum('percentage');
        if (abs($totalPercentage - 100) > 0.01) {
            return back()->withErrors(['distributions' => 'Total persentase harus tepat 100%.']);
        }

        DB::transaction(function () use ($validated) {
            Project::query()
                ->whereKey($validated['project_id'])
                ->update([
                    'team_distribution_rate' => number_format((float) $validated['distribution_rate'], 2, '.', ''),
                ]);

            // Ambil distribusi yang sudah dibayar terlebih dahulu agar payment link tidak hilang
            $paidDistributions = TeamDistribution::query()
                ->where('project_id', $validated['project_id'])
                ->whereNotNull('paid_at')
                ->get()
                ->keyBy('user_id');

            // Hapus hanya yang BELUM dibayar untuk menghindari kehilangan link cash_out
            TeamDistribution::query()
                ->where('project_id', $validated['project_id'])
                ->whereNull('paid_at')
                ->delete();

            foreach ($validated['distributions'] as $dist) {
                $existing = $paidDistributions->get($dist['user_id']);

                if ($existing) {
                    // Update record yang sudah ada (sudah dibayar): hanya perbarui kalkulasi,
                    // pertahankan paid_at, cash_out_id agar payment link tidak hilang
                    $existing->update([
                        'role_in_project' => $dist['role_in_project'],
                        'percentage'      => $dist['percentage'],
                        'base_pay'        => $dist['base_pay'],
                        'bonus'           => $dist['bonus'],
                        'total_pay'       => $dist['base_pay'] + $dist['bonus'],
                    ]);
                } else {
                    TeamDistribution::create([
                        'project_id'      => $validated['project_id'],
                        'user_id'         => $dist['user_id'],
                        'role_in_project' => $dist['role_in_project'],
                        'percentage'      => $dist['percentage'],
                        'base_pay'        => $dist['base_pay'],
                        'bonus'           => $dist['bonus'],
                        'total_pay'       => $dist['base_pay'] + $dist['bonus'],
                    ]);
                }
            }
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Pembagian tim berhasil disimpan.']);
    }

    private function activeTeamRoles()
    {
        if (! TeamRole::query()->exists()) {
            foreach (['lead', 'developer', 'designer', 'qa'] as $name) {
                TeamRole::query()->create(['name' => $name, 'is_active' => true]);
            }
        }

        return TeamRole::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function projectDistributionSnapshot(Project $project): array
    {
        $project->loadMissing(['cashIns', 'referrals', 'cashOuts', 'materials.product', 'convertedBudget.items', 'teamDistributions']);

        $cashInTotal = (float) $project->cashIns->sum('amount');
        $cashOutTotal = (float) $project->cashOuts->sum('amount');
        $materialItems = $project->materials;
        $referralTotal = (float) $project->referrals->sum('commission_amount');
        $operationalTotal = (float) $project->cashOuts->where('category', 'operasional')->sum('amount');
        // Gunakan nilai kontrak (total_value / budget / material) bukan cashIn
        // agar distributable amount tidak berubah-ubah seiring termin masuk
        $contractValue = $project->resolveListTotalValue();
        $paidAmount = $cashInTotal;
        $materialCostTotal = (float) $materialItems
            ->filter(fn ($item) => $item->product?->product_type !== 'service')
            ->sum(fn ($item) => (float) $item->planned_qty * (float) $item->unit_cost);
        $serviceCostTotal = (float) $materialItems
            ->filter(fn ($item) => $item->product?->product_type === 'service')
            ->sum(fn ($item) => (float) $item->planned_qty * (float) $item->unit_cost);
        $directCostTotal = $materialCostTotal + $serviceCostTotal;
        // Margin = paid - biaya langsung - operasional - referral (semua komponen pengurang)
        $marginAmount = $paidAmount - $directCostTotal - $operationalTotal - $referralTotal;

        $distributionRate = (float) ($project->team_distribution_rate ?? 30);
        $companyReserveAmount = max($marginAmount * ($distributionRate / 100), 0);
        $distributableAmount = max($marginAmount - $companyReserveAmount, 0);
        $totalDistributed = (float) $project->teamDistributions->sum('total_pay');

        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'status_key' => Str::lower((string) $project->status),
            'client_name' => $project->client_name,
            'started_at' => $project->started_at?->format('Y-m-d'),
            'total_value' => $contractValue,
            'paid_amount' => $paidAmount,
            'material_cost_total' => $materialCostTotal,
            'service_cost_total' => $serviceCostTotal,
            'direct_cost_total' => $directCostTotal,
            'cash_in_total' => $cashInTotal,
            'cash_out_total' => $cashOutTotal,
            'referral_total' => $referralTotal,
            'operational_total' => $operationalTotal,
            'margin_amount' => $marginAmount,
            'margin_source' => 'paid_minus_material_service_operational',
            'distribution_rate' => $distributionRate,
            'company_reserve_amount' => $companyReserveAmount,
            'distributable_amount' => $distributableAmount,
            'distributed_total' => $totalDistributed,
            'remaining_distributable' => $distributableAmount - $totalDistributed,
            'team_member_count' => $project->teamDistributions->count(),
        ];
    }
}
