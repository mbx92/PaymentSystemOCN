<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\Uom;
use App\Models\UomConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ERPInventoryMasterDataController extends Controller
{
    public function categories(): Response
    {
        return Inertia::render('ERP/Inventory/Categories', [
            'categories' => ProductCategory::query()->orderBy('name')->get(),
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

    public function uoms(): Response
    {
        return Inertia::render('ERP/Inventory/Uoms', [
            'uoms' => Uom::query()->orderBy('code')->get(),
            'conversions' => UomConversion::query()->with('fromUom', 'toUom')->latest()->get(),
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
