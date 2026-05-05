<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with(['payments'])
            ->when($request->search, fn ($q) => $q->where('name', 'ilike', "%{$request->search}%")
                ->orWhere('client_name', 'ilike', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status));

        $projects = $query->latest()->paginate(15)->withQueryString()
            ->through(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'client_name' => $p->client_name,
                'status'      => $p->status,
                'total_value' => (float) $p->total_value,
                'started_at'  => $p->started_at?->format('Y-m-d'),
                'paid_terms'  => $p->payments->where('paid_at', '!=', null)->count(),
                'total_terms' => $p->payments->count(),
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters'  => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Projects/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'client_name'    => 'required|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'total_value'    => 'required|numeric|min:0.01',
            'status'         => 'required|in:negosiasi,berjalan,selesai,dibatalkan',
            'started_at'     => 'nullable|date',
            'finished_at'    => 'nullable|date|after_or_equal:started_at',
            'description'    => 'nullable|string',
            'payments'       => 'required|array|min:1|max:20',
            'payments.*.percentage' => 'required|numeric|min:0.01|max:100',
            'payments.*.note'       => 'nullable|string|max:500',
        ]);

        $this->assertPaymentsTotalHundredPercent($validated['payments']);

        DB::transaction(function () use ($validated) {
            $project = Project::create(collect($validated)->except('payments')->all());
            $this->createPaymentRows($project, $validated['payments']);
        });

        return redirect()->route('projects.index')->with('flash', ['type' => 'success', 'message' => 'Project berhasil ditambahkan.']);
    }

    public function show(Project $project)
    {
        $project->load(['payments', 'cashIns.creator', 'cashOuts.creator', 'teamDistributions.user', 'referrals']);

        return Inertia::render('Projects/Show', [
            'project' => [
                'id'            => $project->id,
                'name'          => $project->name,
                'client_name'   => $project->client_name,
                'client_contact'=> $project->client_contact,
                'total_value'   => (float) $project->total_value,
                'status'        => $project->status,
                'started_at'    => $project->started_at?->format('Y-m-d'),
                'finished_at'   => $project->finished_at?->format('Y-m-d'),
                'description'   => $project->description,
                'payments'      => $project->payments->map(fn ($p) => [
                    'id'          => $p->id,
                    'term_number' => $p->term_number,
                    'percentage'  => (float) $p->percentage,
                    'amount'      => (float) $p->amount,
                    'paid_at'     => $p->paid_at?->format('Y-m-d'),
                    'note'        => $p->note,
                ]),
                'cash_ins'  => $project->cashIns->map(fn ($c) => [
                    'id'           => $c->id,
                    'category'     => $c->category,
                    'amount'       => (float) $c->amount,
                    'date'         => $c->date->format('Y-m-d'),
                    'note'         => $c->note,
                    'creator_name' => $c->creator->name,
                ]),
                'cash_outs' => $project->cashOuts->map(fn ($c) => [
                    'id'             => $c->id,
                    'category'       => $c->category,
                    'amount'         => (float) $c->amount,
                    'date'           => $c->date->format('Y-m-d'),
                    'note'           => $c->note,
                    'recipient_name' => $c->recipient_name,
                    'creator_name'   => $c->creator->name,
                ]),
                'team_distributions' => $project->teamDistributions->map(fn ($d) => [
                    'id'              => $d->id,
                    'user_id'         => $d->user_id,
                    'user_name'       => $d->user->name,
                    'role_in_project' => $d->role_in_project,
                    'percentage'      => (float) $d->percentage,
                    'base_pay'        => (float) $d->base_pay,
                    'bonus'           => (float) $d->bonus,
                    'total_pay'       => (float) $d->total_pay,
                ]),
                'referrals' => $project->referrals->map(fn ($r) => [
                    'id'                => $r->id,
                    'referrer_name'     => $r->referrer_name,
                    'commission_amount' => (float) $r->commission_amount,
                    'paid_at'           => $r->paid_at?->format('Y-m-d'),
                    'note'              => $r->note,
                ]),
                'summary' => [
                    'total_cash_in'            => $project->total_cash_in,
                    'total_cash_out'           => $project->total_cash_out,
                    'profit'                   => $project->profit,
                    'total_referral_commission'=> $project->total_referral_commission,
                    'total_operational'        => $project->total_operational,
                    'net_team_value'           => $project->net_team_value,
                ],
            ],
        ]);
    }

    public function edit(Project $project)
    {
        $project->load('payments');

        $canEditPayments = ! $project->payments()->whereNotNull('paid_at')->exists();

        return Inertia::render('Projects/Edit', [
            'project' => [
                'id'             => $project->id,
                'name'           => $project->name,
                'client_name'    => $project->client_name,
                'client_contact' => $project->client_contact,
                'total_value'    => (float) $project->total_value,
                'status'         => $project->status,
                'started_at'     => $project->started_at?->format('Y-m-d') ?? '',
                'finished_at'    => $project->finished_at?->format('Y-m-d') ?? '',
                'description'    => $project->description ?? '',
            ],
            'payments' => $project->payments->map(fn ($p) => [
                'id'          => $p->id,
                'term_number' => $p->term_number,
                'percentage'  => (float) $p->percentage,
                'amount'      => (float) $p->amount,
                'note'        => $p->note ?? '',
                'paid_at'     => $p->paid_at?->format('Y-m-d'),
            ]),
            'can_edit_payments' => $canEditPayments,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $canEditPayments = ! $project->payments()->whereNotNull('paid_at')->exists();

        if ($request->has('payments') && ! $canEditPayments) {
            throw ValidationException::withMessages([
                'payments' => 'Jadwal termin tidak dapat diubah karena sudah ada pembayaran yang ditandai lunas.',
            ]);
        }

        $rules = [
            'name'           => 'required|string|max:255',
            'client_name'    => 'required|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'total_value'    => 'required|numeric|min:0.01',
            'status'         => 'required|in:negosiasi,berjalan,selesai,dibatalkan',
            'started_at'     => 'nullable|date',
            'finished_at'    => 'nullable|date|after_or_equal:started_at',
            'description'    => 'nullable|string',
        ];

        if ($canEditPayments) {
            $rules['payments'] = 'required|array|min:1|max:20';
            $rules['payments.*.percentage'] = 'required|numeric|min:0.01|max:100';
            $rules['payments.*.note'] = 'nullable|string|max:500';
        }

        $validated = $request->validate($rules);

        if ($canEditPayments) {
            $this->assertPaymentsTotalHundredPercent($validated['payments']);
        }

        DB::transaction(function () use ($project, $validated, $canEditPayments) {
            $project->update(collect($validated)->except('payments')->all());

            if ($canEditPayments) {
                $this->replacePaymentSchedule($project, $validated['payments']);
            }
        });

        return redirect()->route('projects.show', $project)->with('flash', ['type' => 'success', 'message' => 'Project berhasil diperbarui.']);
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')->with('flash', ['type' => 'success', 'message' => 'Project berhasil dihapus.']);
    }

    protected function assertPaymentsTotalHundredPercent(array $payments): void
    {
        $totalPct = collect($payments)->sum('percentage');
        if (abs($totalPct - 100) > 0.02) {
            throw ValidationException::withMessages([
                'payments' => 'Total persentase termin harus tepat 100% (saat ini: '.round($totalPct, 2).'%).',
            ]);
        }
    }

    protected function createPaymentRows(Project $project, array $payments): void
    {
        $totalValue = (float) $project->total_value;
        $n          = count($payments);
        $assigned   = 0.0;

        foreach ($payments as $i => $term) {
            $pct = (float) $term['percentage'];
            if ($i === $n - 1) {
                $amount = round($totalValue - $assigned, 2);
            } else {
                $amount = round($totalValue * ($pct / 100), 2);
                $assigned += $amount;
            }

            ProjectPayment::create([
                'project_id'  => $project->id,
                'term_number' => $i + 1,
                'percentage'  => $pct,
                'amount'      => $amount,
                'note'        => $term['note'] ?? null,
            ]);
        }
    }

    protected function replacePaymentSchedule(Project $project, array $payments): void
    {
        $project->payments()->delete();
        $this->createPaymentRows($project, $payments);
    }
}
