<?php

namespace App\Http\Controllers;

use App\ERP\CRM\Models\CrmActivity;
use App\ERP\CRM\Models\CrmCustomer;
use App\ERP\CRM\Models\CrmLead;
use App\ERP\CRM\Models\CrmPipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CrmActivity::query()->with([
            'lead:id,name',
            'customer:id,name,company',
            'pipeline:id,code,title',
            'user:id,name',
        ]);

        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('subject', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $activities = $query->orderByDesc('activity_date')->paginate($this->resolvedPerPage($request))->withQueryString()
            ->through(fn (CrmActivity $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'subject' => $a->subject,
                'description' => $a->description,
                'activity_date' => $a->activity_date?->format('Y-m-d H:i'),
                'next_action_date' => $a->next_action_date?->format('Y-m-d H:i'),
                'next_action_note' => $a->next_action_note,
                'status' => $a->status,
                'crm_lead_id' => $a->crm_lead_id,
                'lead_name' => $a->lead?->name,
                'crm_customer_id' => $a->crm_customer_id,
                'customer_name' => $a->customer ? ($a->customer->company ?: $a->customer->name) : null,
                'crm_pipeline_id' => $a->crm_pipeline_id,
                'pipeline_title' => $a->pipeline ? "{$a->pipeline->code} — {$a->pipeline->title}" : null,
                'user_id' => $a->user_id,
                'user_name' => $a->user?->name,
                'created_at' => $a->created_at?->format('Y-m-d H:i'),
            ]);

        $leads = CrmLead::query()->whereNotIn('status', ['lost'])->orderBy('name')->get(['id', 'name']);
        $customers = CrmCustomer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'company']);
        $pipelines = CrmPipeline::query()->whereNotIn('stage', ['closed_won', 'closed_lost'])->orderByDesc('updated_at')->get(['id', 'code', 'title']);

        return Inertia::render('ERP/CRM/Activities', [
            'activities' => $activities,
            'leads' => $leads,
            'customers' => $customers,
            'pipelines' => $pipelines,
            'filters' => $this->filtersWithPerPage($request, ['q', 'type', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:30',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:3000',
            'activity_date' => 'required|date',
            'next_action_date' => 'nullable|date',
            'next_action_note' => 'nullable|string|max:255',
            'status' => 'required|string|max:20',
            'crm_lead_id' => 'nullable|exists:crm_leads,id',
            'crm_customer_id' => 'nullable|exists:crm_customers,id',
            'crm_pipeline_id' => 'nullable|exists:crm_pipelines,id',
        ]);

        $validated['user_id'] = $request->user()->id;

        CrmActivity::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Aktivitas berhasil dicatat.']);
    }

    public function update(Request $request, CrmActivity $crmActivity): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:30',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:3000',
            'activity_date' => 'required|date',
            'next_action_date' => 'nullable|date',
            'next_action_note' => 'nullable|string|max:255',
            'status' => 'required|string|max:20',
            'crm_lead_id' => 'nullable|exists:crm_leads,id',
            'crm_customer_id' => 'nullable|exists:crm_customers,id',
            'crm_pipeline_id' => 'nullable|exists:crm_pipelines,id',
        ]);

        $crmActivity->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Aktivitas diperbarui.']);
    }

    public function destroy(CrmActivity $crmActivity): RedirectResponse
    {
        $crmActivity->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Aktivitas dihapus.']);
    }
}
