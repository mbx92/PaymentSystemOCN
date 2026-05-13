<?php

namespace App\Http\Controllers;

use App\ERP\CRM\Models\CrmCustomer;
use App\ERP\CRM\Models\CrmLead;
use App\ERP\CRM\Models\CrmPipeline;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CrmPipelineController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CrmPipeline::query()->with(['customer:id,name,company', 'lead:id,name', 'pic:id,name']);

        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        if ($stage = $request->input('stage')) {
            $query->where('stage', $stage);
        }

        $pipelines = $query->orderByDesc('updated_at')->paginate($this->resolvedPerPage($request))->withQueryString()
            ->through(fn (CrmPipeline $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'title' => $p->title,
                'crm_customer_id' => $p->crm_customer_id,
                'customer_name' => $p->customer ? ($p->customer->company ?: $p->customer->name) : null,
                'crm_lead_id' => $p->crm_lead_id,
                'lead_name' => $p->lead?->name,
                'stage' => $p->stage,
                'deal_value' => (float) $p->deal_value,
                'win_probability' => $p->win_probability,
                'expected_close_date' => $p->expected_close_date?->format('Y-m-d'),
                'pic_user_id' => $p->pic_user_id,
                'pic_name' => $p->pic?->name,
                'notes' => $p->notes,
                'updated_at' => $p->updated_at?->format('Y-m-d H:i'),
            ]);

        $customers = CrmCustomer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'company']);
        $leads = CrmLead::query()->whereNotIn('status', ['won', 'lost'])->orderBy('name')->get(['id', 'name', 'company']);
        $users = User::query()
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'manajer']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ERP/CRM/Pipelines', [
            'pipelines' => $pipelines,
            'customers' => $customers,
            'leads' => $leads,
            'users' => $users,
            'filters' => $this->filtersWithPerPage($request, ['q', 'stage']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'crm_customer_id' => 'nullable|exists:crm_customers,id',
            'crm_lead_id' => 'nullable|exists:crm_leads,id',
            'stage' => 'required|string|max:30',
            'deal_value' => 'nullable|numeric|min:0',
            'win_probability' => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'pic_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $validated['deal_value'] = $validated['deal_value'] ?? 0;
        $validated['win_probability'] = $validated['win_probability'] ?? 0;
        $validated['code'] = $this->generateCode();

        CrmPipeline::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Pipeline berhasil ditambahkan.']);
    }

    public function update(Request $request, CrmPipeline $crmPipeline): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'crm_customer_id' => 'nullable|exists:crm_customers,id',
            'crm_lead_id' => 'nullable|exists:crm_leads,id',
            'stage' => 'required|string|max:30',
            'deal_value' => 'nullable|numeric|min:0',
            'win_probability' => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'pic_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $validated['deal_value'] = $validated['deal_value'] ?? 0;
        $validated['win_probability'] = $validated['win_probability'] ?? 0;

        $crmPipeline->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Pipeline diperbarui.']);
    }

    public function destroy(CrmPipeline $crmPipeline): RedirectResponse
    {
        if ($crmPipeline->activities()->exists()) {
            return back()->with('flash', ['type' => 'warning', 'message' => 'Tidak dapat menghapus pipeline yang sudah punya aktivitas.']);
        }

        $crmPipeline->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Pipeline dihapus.']);
    }

    private function generateCode(): string
    {
        $last = DB::table('crm_pipelines')
            ->where('code', 'like', 'PIP-%')
            ->orderByDesc('code')
            ->value('code');

        $seq = 1;
        if ($last && preg_match('/PIP-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'PIP-'.str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
