<?php

namespace App\Http\Controllers;

use App\ERP\Core\Models\Company;
use App\Http\Requests\ERP\StoreCompanyRequest;
use App\Http\Requests\ERP\UpdateCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        Company::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        Cache::forget('active_companies');

        return back()->with('flash', ['type' => 'success', 'message' => 'Perusahaan berhasil ditambahkan.']);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $validated = $request->validated();

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
            ...$validated,
            'is_active' => $isActive,
        ]);

        Cache::forget('active_companies');

        return back()->with('flash', ['type' => 'success', 'message' => 'Data perusahaan berhasil diperbarui.']);
    }

    public function toggleActive(Company $company): RedirectResponse
    {
        $newStatus = ! $company->is_active;

        if (! $newStatus) {
            $others = Company::query()
                ->where('is_active', true)
                ->whereKeyNot($company->id)
                ->exists();
            if (! $others) {
                return back()->with('flash', ['type' => 'warning', 'message' => 'Harus ada minimal satu perusahaan aktif.']);
            }
        }

        $company->update(['is_active' => $newStatus]);

        Cache::forget('active_companies');

        $msg = $newStatus ? 'Perusahaan diaktifkan.' : 'Perusahaan dinonaktifkan.';

        return back()->with('flash', ['type' => 'success', 'message' => $msg]);
    }
}
