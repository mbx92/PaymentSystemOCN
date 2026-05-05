<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TeamDistribution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamDistributionController extends Controller
{
    public function calculator(Request $request)
    {
        $projectId = $request->query('project_id');

        if (filled($projectId) && ! Str::isUuid($projectId)) {
            return redirect()->route('team-distribution.calculator');
        }

        $projects = Project::whereIn('status', ['berjalan', 'selesai'])
            ->orderBy('name')
            ->get(['id', 'name', 'total_value', 'status']);

        $members = User::role('anggota')->orWhereHas('roles', fn ($q) => $q->where('name', 'manajer'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedProject = null;
        $existingDistributions = [];

        if (filled($projectId)) {
            $project = Project::with(['referrals', 'cashOuts' => fn ($q) => $q->where('category', 'operasional')])
                ->findOrFail($projectId);

            $referralTotal    = (float) $project->referrals()->sum('commission_amount');
            $operationalTotal = (float) $project->cashOuts()->where('category', 'operasional')->sum('amount');
            $netValue         = $project->total_value - $referralTotal - $operationalTotal;

            $selectedProject = [
                'id'                => $project->id,
                'name'              => $project->name,
                'total_value'       => (float) $project->total_value,
                'referral_total'    => $referralTotal,
                'operational_total' => $operationalTotal,
                'net_value'         => $netValue,
            ];

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
            'projects'              => $projects,
            'members'               => $members,
            'selectedProject'       => $selectedProject,
            'existingDistributions' => $existingDistributions,
            'selectedProjectId'     => filled($projectId) ? $projectId : null,
        ]);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'project_id'    => 'required|uuid|exists:projects,id',
            'distributions' => 'required|array|min:1',
            'distributions.*.user_id'         => 'required|exists:users,id',
            'distributions.*.role_in_project' => 'required|in:lead,developer,designer,qa',
            'distributions.*.percentage'      => 'required|numeric|min:0|max:100',
            'distributions.*.base_pay'        => 'required|numeric|min:0',
            'distributions.*.bonus'           => 'required|numeric|min:0',
        ]);

        $totalPercentage = collect($validated['distributions'])->sum('percentage');
        if (abs($totalPercentage - 100) > 0.01) {
            return back()->withErrors(['distributions' => 'Total persentase harus tepat 100%.']);
        }

        DB::transaction(function () use ($validated) {
            TeamDistribution::where('project_id', $validated['project_id'])->delete();

            foreach ($validated['distributions'] as $dist) {
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
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Pembagian tim berhasil disimpan.']);
    }
}
