<?php

namespace App\Http\Controllers;

use App\ERP\CRM\Models\CrmLead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CrmLeadController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CrmLead::query()->with('pic:id,name');

        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        $leads = $query->orderByDesc('created_at')->paginate($this->resolvedPerPage($request))->withQueryString()
            ->through(fn (CrmLead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'company' => $lead->company,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'source' => $lead->source,
                'status' => $lead->status,
                'estimated_value' => (float) $lead->estimated_value,
                'pic_user_id' => $lead->pic_user_id,
                'pic_name' => $lead->pic?->name,
                'notes' => $lead->notes,
                'created_at' => $lead->created_at?->format('Y-m-d H:i'),
            ]);

        $users = Cache::remember('crm_pic_users', now()->addMinutes(15), function () {
            return User::query()
                ->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'manajer']))
                ->orderBy('name')
                ->get(['id', 'name']);
        });

        return Inertia::render('ERP/CRM/Leads', [
            'leads' => $leads,
            'users' => $users,
            'filters' => $this->filtersWithPerPage($request, ['q', 'status', 'source']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => 'required|string|max:60',
            'status' => 'required|string|max:30',
            'estimated_value' => 'nullable|numeric|min:0',
            'pic_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $validated['estimated_value'] = $validated['estimated_value'] ?? 0;

        CrmLead::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Lead berhasil ditambahkan.']);
    }

    public function update(Request $request, CrmLead $crmLead): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => 'required|string|max:60',
            'status' => 'required|string|max:30',
            'estimated_value' => 'nullable|numeric|min:0',
            'pic_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $validated['estimated_value'] = $validated['estimated_value'] ?? 0;

        $crmLead->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Data lead diperbarui.']);
    }

    public function destroy(CrmLead $crmLead): RedirectResponse
    {
        $crmLead->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Lead dihapus.']);
    }
}
