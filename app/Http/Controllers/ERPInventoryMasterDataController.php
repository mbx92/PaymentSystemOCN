<?php

namespace App\Http\Controllers;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\ProductCategory;
use App\Models\Uom;
use App\Models\UomConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ERPInventoryMasterDataController extends Controller
{
    public function warehouses(Request $request): Response
    {
        $query = Warehouse::query()->orderBy('name');

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($inner) use ($term): void {
                $inner->where('code', 'like', '%'.$term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%')
                    ->orWhere('address', 'like', '%'.$term.'%');
            });
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return Inertia::render('ERP/Inventory/Warehouses', [
            'warehouses' => $query
                ->paginate($this->resolvedPerPage($request))
                ->withQueryString()
                ->through(fn (Warehouse $warehouse) => [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                    'address' => $warehouse->address,
                    'status' => $warehouse->is_active ? 'active' : 'inactive',
                ]),
            'filters' => $this->filtersWithPerPage($request, ['q', 'status']),
        ]);
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32|unique:warehouses,code',
            'name' => 'required|string|max:120',
            'address' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        Warehouse::query()->create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'is_active' => $validated['status'] === 'active',
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Warehouse berhasil ditambahkan.']);
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32|unique:warehouses,code,'.$warehouse->id,
            'name' => 'required|string|max:120',
            'address' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $warehouse->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'is_active' => $validated['status'] === 'active',
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Warehouse berhasil diperbarui.']);
    }

    public function categories(Request $request): Response
    {
        return Inertia::render('ERP/Inventory/Categories', [
            'categories' => ProductCategory::query()
                ->orderBy('name')
                ->paginate($this->resolvedPerPage($request))
                ->withQueryString(),
            'filters' => $this->filtersWithPerPage($request, []),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:product_categories,name',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        ProductCategory::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Kategori berhasil ditambahkan.']);
    }

    public function uoms(Request $request): Response
    {
        $perPage = $this->resolvedPerPage($request);

        return Inertia::render('ERP/Inventory/Uoms', [
            'uoms' => Uom::query()->orderBy('code')->paginate($perPage, ['*'], 'uoms_page')->withQueryString(),
            'conversions' => UomConversion::query()->with('fromUom', 'toUom')->latest()->paginate($perPage, ['*'], 'conversions_page')->withQueryString(),
            'uomsForSelect' => Uom::query()->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => $this->filtersWithPerPage($request, []),
        ]);
    }

    public function storeUom(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:uoms,code',
            'name' => 'required|string|max:50',
            'status' => 'required|in:active,inactive',
        ]);

        Uom::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'UoM berhasil ditambahkan.']);
    }

    public function storeConversion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_uom_id' => 'required|exists:uoms,id',
            'to_uom_id' => 'required|exists:uoms,id|different:from_uom_id',
            'multiplier' => 'required|numeric|gt:0',
        ]);

        UomConversion::query()->updateOrCreate(
            ['from_uom_id' => $validated['from_uom_id'], 'to_uom_id' => $validated['to_uom_id']],
            ['multiplier' => $validated['multiplier']]
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Konversi UoM berhasil disimpan.']);
    }
}
