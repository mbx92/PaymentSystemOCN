<?php

namespace App\Http\Controllers;

use App\ERP\Core\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ERPCompanyMasterController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Company::query()->orderBy('name');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('legal_name', 'like', $term)
                    ->orWhere('tax_id', 'like', $term);
            });
        }

        $companies = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (Company $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'legal_name' => $c->legal_name,
                'tax_id' => $c->tax_id,
                'email' => $c->email,
                'phone' => $c->phone,
                'address' => $c->address,
                'is_active' => $c->is_active,
            ]);

        return Inertia::render('ERP/Admin/Companies', [
            'companies' => $companies,
            'filters' => $this->filtersWithPerPage($request, ['q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        Company::query()->create([
            'name' => trim($validated['name']),
            'legal_name' => $validated['legal_name'] ? trim($validated['legal_name']) : null,
            'tax_id' => $validated['tax_id'] ? trim($validated['tax_id']) : null,
            'email' => $validated['email'] ? trim($validated['email']) : null,
            'phone' => $validated['phone'] ? trim($validated['phone']) : null,
            'address' => $validated['address'] ? trim($validated['address']) : null,
            'is_active' => true,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Perusahaan berhasil ditambahkan.']);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $isActive = array_key_exists('is_active', $validated)
            ? (bool) $validated['is_active']
            : $company->is_active;

        if (! $isActive && $company->is_active) {
            $others = Company::query()
                ->where('is_active', true)
                ->whereKeyNot($company->id)
                ->exists();
            if (! $others) {
                throw ValidationException::withMessages([
                    'is_active' => 'Harus ada minimal satu perusahaan aktif.',
                ]);
            }
        }

        $company->update([
            'name' => trim($validated['name']),
            'legal_name' => filled($validated['legal_name'] ?? null) ? trim((string) $validated['legal_name']) : null,
            'tax_id' => filled($validated['tax_id'] ?? null) ? trim((string) $validated['tax_id']) : null,
            'email' => filled($validated['email'] ?? null) ? trim((string) $validated['email']) : null,
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
            'address' => filled($validated['address'] ?? null) ? trim((string) $validated['address']) : null,
            'is_active' => $isActive,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Data perusahaan berhasil diperbarui.']);
    }
}
