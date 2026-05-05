<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use App\Models\ProductCategory;
use App\Models\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ERPMasterProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = MasterProduct::query()
            ->when($request->filled('sales_channel'), fn ($q) => $q->where('sales_channel', $request->string('sales_channel')->toString()))
            ->when($request->filled('product_type'), fn ($q) => $q->where('product_type', $request->string('product_type')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'ilike', "%{$term}%")
                        ->orWhere('sku', 'ilike', "%{$term}%");
                });
            })
            ->latest()
            ->get();

        return Inertia::render('ERP/MasterProducts/Index', [
            'products' => $products,
            'filters' => $request->only(['q', 'sales_channel', 'product_type']),
            'categories' => ProductCategory::query()->where('status', 'active')->orderBy('name')->get(['name']),
            'uoms' => Uom::query()->where('status', 'active')->orderBy('code')->get(['code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:64|unique:master_products,sku',
            'barcode' => 'nullable|string|max:100|unique:master_products,barcode',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100|exists:product_categories,name',
            'uom' => 'required|string|max:20|exists:uoms,code',
            'sales_channel' => 'required|in:pos,project,both',
            'product_type' => 'required|in:finished_goods,project_material',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'lead_time_days' => 'nullable|integer|min:1|max:365',
        ]);

        $validated['lead_time_days'] = $validated['lead_time_days'] ?? 7;

        MasterProduct::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Produk berhasil ditambahkan.']);
    }

    public function show(MasterProduct $masterProduct): Response
    {
        return Inertia::render('ERP/MasterProducts/Show', [
            'product' => $masterProduct,
        ]);
    }

    public function destroy(MasterProduct $masterProduct): RedirectResponse
    {
        $masterProduct->delete();

        return redirect()->route('erp.master-products.index')
            ->with('flash', ['type' => 'success', 'message' => 'Produk berhasil dihapus.']);
    }
}
