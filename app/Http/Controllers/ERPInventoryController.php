<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use App\Models\ProductStockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ERPInventoryController extends Controller
{
    public function stockManagement(): Response
    {
        return Inertia::render('ERP/Inventory/StockManagement', [
            'products' => MasterProduct::query()->orderBy('name')->get(),
        ]);
    }

    public function updateStock(Request $request, MasterProduct $masterProduct): RedirectResponse
    {
        $validated = $request->validate([
            'min_stock' => 'required|integer|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        $masterProduct->update([
            'min_stock' => $validated['min_stock'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Minimum stok berhasil diperbarui.']);
    }

    public function stockOpname(): Response
    {
        return Inertia::render('ERP/Inventory/StockOpname', [
            'products' => MasterProduct::query()->orderBy('name')->get(['id', 'sku', 'name', 'stock', 'uom']),
        ]);
    }

    public function storeStockOpname(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:master_products,id',
            'physical_stock' => 'required|integer|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $product = MasterProduct::query()->findOrFail($validated['product_id']);
        $oldStock = $product->stock;
        $newStock = (int) $validated['physical_stock'];

        DB::transaction(function () use ($product, $oldStock, $newStock, $validated): void {
            $product->update(['stock' => $newStock]);

            $diff = $newStock - $oldStock;
            if ($diff !== 0) {
                ProductStockMovement::query()->create([
                    'master_product_id' => $product->id,
                    'movement_date' => now()->toDateString(),
                    'movement_type' => $diff > 0 ? 'opname_in' : 'opname_out',
                    'qty' => abs($diff),
                    'note' => $validated['note'] ?? 'Stock opname',
                ]);
            }
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Stock opname berhasil disimpan.']);
    }

    public function stockReport(Request $request): Response
    {
        $selectedYear = (int) $request->integer('year', now()->year);
        $selectedProductId = $request->filled('product_id') ? (int) $request->integer('product_id') : null;

        $products = MasterProduct::query()->orderBy('name')->get();

        $stockChart = $products->take(10)->map(fn (MasterProduct $item) => [
            'label' => $item->sku,
            'stock' => $item->stock,
        ])->values();

        $lowStockAlerts = $products
            ->filter(fn (MasterProduct $item) => $item->stock <= $item->min_stock)
            ->values()
            ->map(fn (MasterProduct $item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'stock' => $item->stock,
                'min_stock' => $item->min_stock,
            ]);

        $topSelling = $products
            ->sortByDesc('total_sold')
            ->take(5)
            ->values()
            ->map(fn (MasterProduct $item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'total_sold' => $item->total_sold,
            ]);

        $summary = [
            'total_products' => $products->count(),
            'low_stock_count' => $lowStockAlerts->count(),
            'total_units_in_stock' => $products->sum('stock'),
            'total_units_sold' => $products->sum('total_sold'),
        ];

        $monthlyTrend = collect(range(1, 12))->map(function (int $month) use ($selectedYear, $selectedProductId) {
            $rows = ProductStockMovement::query()
                ->whereYear('movement_date', $selectedYear)
                ->whereMonth('movement_date', $month)
                ->when($selectedProductId, fn ($q) => $q->where('master_product_id', $selectedProductId))
                ->get(['movement_type', 'qty']);

            $in = $rows
                ->filter(fn (ProductStockMovement $item) => str_contains($item->movement_type, 'in'))
                ->sum('qty');

            $out = $rows
                ->filter(fn (ProductStockMovement $item) => str_contains($item->movement_type, 'out'))
                ->sum('qty');

            return [
                'month' => $month,
                'in' => (float) $in,
                'out' => (float) $out,
            ];
        });

        $reorderSuggestions = $products
            ->map(function (MasterProduct $item) {
                $dailyUsage = $item->total_sold > 0 ? $item->total_sold / 30 : 0;
                $leadDemand = (int) ceil($dailyUsage * max($item->lead_time_days, 1));
                $targetStock = $item->min_stock + $leadDemand;
                $suggestedQty = max($targetStock - $item->stock, 0);

                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'stock' => $item->stock,
                    'min_stock' => $item->min_stock,
                    'lead_time_days' => $item->lead_time_days,
                    'suggested_qty' => $suggestedQty,
                ];
            })
            ->filter(fn (array $row) => $row['suggested_qty'] > 0)
            ->sortByDesc('suggested_qty')
            ->take(10)
            ->values();

        return Inertia::render('ERP/Inventory/StockReport', [
            'summary' => $summary,
            'stockChart' => $stockChart,
            'lowStockAlerts' => $lowStockAlerts,
            'topSelling' => $topSelling,
            'monthlyTrend' => $monthlyTrend,
            'reorderSuggestions' => $reorderSuggestions,
            'filters' => [
                'year' => $selectedYear,
                'product_id' => $selectedProductId,
            ],
            'years' => range(now()->year, now()->year - 4),
            'products' => $products->map(fn (MasterProduct $item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
            ]),
        ]);
    }
}
