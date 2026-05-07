<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use App\Models\MasterProductUomMapping;
use App\Models\ProductCategory;
use App\Models\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $masterProduct->load('uomMappings');

        return Inertia::render('ERP/MasterProducts/Show', [
            'product' => $masterProduct,
            'uomMappings' => $masterProduct->uomMappings->map(fn (MasterProductUomMapping $mapping) => [
                'id' => $mapping->id,
                'uom_code' => $mapping->uom_code,
                'multiplier' => (float) $mapping->multiplier,
                'price_operation' => $mapping->price_operation ?: 'multiply',
                'selling_price' => (float) $mapping->selling_price,
                'use_auto_price' => (bool) $mapping->use_auto_price,
                'status' => $mapping->status,
            ]),
            'uoms' => Uom::query()
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['code', 'name']),
            'categories' => ProductCategory::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['name']),
        ]);
    }

    public function update(Request $request, MasterProduct $masterProduct): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:64', Rule::unique('master_products', 'sku')->ignore($masterProduct->id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('master_products', 'barcode')->ignore($masterProduct->id)],
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

        $validated['lead_time_days'] = $validated['lead_time_days'] ?? $masterProduct->lead_time_days ?? 7;
        $masterProduct->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Produk berhasil diperbarui.']);
    }

    public function storeUomMapping(Request $request, MasterProduct $masterProduct): RedirectResponse
    {
        $validated = $request->validate([
            'uom_code' => 'required|string|exists:uoms,code',
            'multiplier' => 'required|numeric|gt:0',
            'price_operation' => 'nullable|in:multiply,divide',
            'selling_price' => 'nullable|numeric|min:0',
            'use_auto_price' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validated['uom_code'] === $masterProduct->uom) {
            return back()->withErrors([
                'uom_code' => 'UoM mapping tidak boleh sama dengan UoM dasar produk.',
            ]);
        }

        $useAutoPrice = (bool) ($validated['use_auto_price'] ?? false);
        $priceOperation = $validated['price_operation'] ?? 'multiply';
        $price = $useAutoPrice
            ? $this->computeMappedPrice((float) $masterProduct->selling_price, (float) $validated['multiplier'], $priceOperation)
            : ((array_key_exists('selling_price', $validated) && $validated['selling_price'] !== null)
            ? (float) $validated['selling_price']
            : $this->computeMappedPrice((float) $masterProduct->selling_price, (float) $validated['multiplier'], $priceOperation));

        MasterProductUomMapping::query()->updateOrCreate(
            [
                'master_product_id' => $masterProduct->id,
                'uom_code' => $validated['uom_code'],
            ],
            [
                'multiplier' => (float) $validated['multiplier'],
                'price_operation' => $priceOperation,
                'selling_price' => $price,
                'use_auto_price' => $useAutoPrice,
                'status' => $validated['status'],
            ]
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Mapping UoM produk berhasil disimpan.']);
    }

    public function updateUomMapping(Request $request, MasterProduct $masterProduct, MasterProductUomMapping $mapping): RedirectResponse
    {
        abort_unless($mapping->master_product_id === $masterProduct->id, 404);

        $validated = $request->validate([
            'multiplier' => 'required|numeric|gt:0',
            'price_operation' => 'nullable|in:multiply,divide',
            'selling_price' => 'nullable|numeric|min:0',
            'use_auto_price' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        $useAutoPrice = (bool) ($validated['use_auto_price'] ?? false);
        $priceOperation = $validated['price_operation'] ?? ($mapping->price_operation ?: 'multiply');
        $price = $useAutoPrice
            ? $this->computeMappedPrice((float) $masterProduct->selling_price, (float) $validated['multiplier'], $priceOperation)
            : ((array_key_exists('selling_price', $validated) && $validated['selling_price'] !== null)
                ? (float) $validated['selling_price']
                : (float) $mapping->selling_price);

        $mapping->update([
            'multiplier' => (float) $validated['multiplier'],
            'price_operation' => $priceOperation,
            'selling_price' => $price,
            'use_auto_price' => $useAutoPrice,
            'status' => $validated['status'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Mapping UoM produk berhasil diperbarui.']);
    }

    public function destroyUomMapping(MasterProduct $masterProduct, MasterProductUomMapping $mapping): RedirectResponse
    {
        abort_unless($mapping->master_product_id === $masterProduct->id, 404);
        $mapping->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Mapping UoM produk berhasil dihapus.']);
    }

    public function destroy(MasterProduct $masterProduct): RedirectResponse
    {
        $masterProduct->delete();

        return redirect()->route('erp.master-products.index')
            ->with('flash', ['type' => 'success', 'message' => 'Produk berhasil dihapus.']);
    }

    private function computeMappedPrice(float $basePrice, float $multiplier, string $operation): float
    {
        if ($operation === 'divide') {
            return round($basePrice / max($multiplier, 0.0001), 2);
        }

        return round($basePrice * $multiplier, 2);
    }
}
