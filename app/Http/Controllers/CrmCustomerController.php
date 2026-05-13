<?php

namespace App\Http\Controllers;

use App\ERP\CRM\Models\CrmCustomer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CrmCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CrmCustomer::query()->with('pic:id,name');

        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        $customers = $query->orderBy('name')->paginate($this->resolvedPerPage($request))->withQueryString()
            ->through(fn (CrmCustomer $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'company' => $c->company,
                'email' => $c->email,
                'phone' => $c->phone,
                'address' => $c->address,
                'business_type' => $c->business_type,
                'tax_id' => $c->tax_id,
                'source' => $c->source,
                'pic_user_id' => $c->pic_user_id,
                'pic_name' => $c->pic?->name,
                'is_active' => $c->is_active,
                'notes' => $c->notes,
                'created_at' => $c->created_at?->format('Y-m-d'),
            ]);

        $users = User::query()
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'manajer']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ERP/CRM/Customers', [
            'customers' => $customers,
            'users' => $users,
            'filters' => $this->filtersWithPerPage($request, ['q', 'is_active', 'source']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'business_type' => 'nullable|string|max:60',
            'tax_id' => 'nullable|string|max:50',
            'source' => 'required|string|max:60',
            'pic_user_id' => 'nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['code'] = $this->generateCode();

        CrmCustomer::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Customer berhasil ditambahkan.']);
    }

    public function update(Request $request, CrmCustomer $crmCustomer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'business_type' => 'nullable|string|max:60',
            'tax_id' => 'nullable|string|max:50',
            'source' => 'required|string|max:60',
            'pic_user_id' => 'nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (array_key_exists('is_active', $validated)) {
            $validated['is_active'] = (bool) $validated['is_active'];
        }

        $crmCustomer->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Data customer diperbarui.']);
    }

    public function destroy(CrmCustomer $crmCustomer): RedirectResponse
    {
        $crmCustomer->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Customer dihapus.']);
    }

    private function generateCode(): string
    {
        $last = DB::table('crm_customers')
            ->where('code', 'like', 'CUST-%')
            ->orderByDesc('code')
            ->value('code');

        $seq = 1;
        if ($last && preg_match('/CUST-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'CUST-'.str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
