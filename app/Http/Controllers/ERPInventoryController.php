<?php

namespace App\Http\Controllers;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProjectMaterial;
use App\Models\ProductStockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ERPInventoryController extends Controller
{
    public function stockManagement(Request $request): Response
    {
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $selectedWarehouseId = (int) $request->integer('warehouse_id', $warehouses->first()?->id ?? 0);

        $products = MasterProduct::query()
            ->orderBy('name')
            ->get()
            ->map(function (MasterProduct $product) use ($selectedWarehouseId) {
                $qty = 0;
                $reservedQty = 0;
                if ($selectedWarehouseId) {
                    $stockRow = MasterProductWarehouseStock::query()
                        ->where('master_product_id', $product->id)
                        ->where('warehouse_id', $selectedWarehouseId)
                        ->first();

                    $qty = (float) ($stockRow?->qty ?? 0);
                    $reservedQty = (float) ($stockRow?->reserved_qty ?? 0);
                }

                $availableQty = $qty - $reservedQty;

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'stock' => $qty,
                    'reserved_qty' => $reservedQty,
                    'available_qty' => $availableQty,
                    'min_stock' => $product->min_stock,
                    'total_sold' => $product->total_sold,
                    'status' => $product->status,
                ];
            });

        $reservedStocks = collect();
        $reservedBreakdownByProduct = collect();
        if ($selectedWarehouseId) {
            $reservedStocks = MasterProductWarehouseStock::query()
                ->with(['product:id,sku,name'])
                ->where('warehouse_id', $selectedWarehouseId)
                ->where('reserved_qty', '>', 0)
                ->orderByDesc('reserved_qty')
                ->get();

            $reservedBreakdownByProduct = ProjectMaterial::query()
                ->with(['project:id,name,status'])
                ->where('warehouse_id', $selectedWarehouseId)
                ->where('reserved_qty', '>', 0)
                ->orderByDesc('reserved_qty')
                ->get()
                ->groupBy('master_product_id')
                ->map(fn ($rows) => $rows->map(fn (ProjectMaterial $material) => [
                    'project_id' => $material->project_id,
                    'project_name' => $material->project?->name,
                    'project_status' => $material->project?->status,
                    'planned_qty' => (float) $material->planned_qty,
                    'reserved_qty' => (float) $material->reserved_qty,
                    'issued_qty' => (float) $material->issued_qty,
                ])->values());
        }

        return Inertia::render('ERP/Inventory/StockManagement', [
            'products' => $products,
            'warehouses' => $warehouses,
            'filters' => [
                'warehouse_id' => $selectedWarehouseId,
            ],
            'reserved_alert' => [
                'count' => $reservedStocks->count(),
                'total_reserved_qty' => (float) $reservedStocks->sum('reserved_qty'),
                'items' => $reservedStocks
                    ->take(5)
                    ->map(fn (MasterProductWarehouseStock $stock) => [
                        'sku' => $stock->product?->sku,
                        'name' => $stock->product?->name,
                        'reserved_qty' => (float) $stock->reserved_qty,
                    ])
                    ->values(),
            ],
            'reserved_breakdown_by_product' => $reservedBreakdownByProduct,
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
            'warehouse_id' => 'nullable|exists:warehouses,id',
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
                    'warehouse_id' => $validated['warehouse_id'] ?? null,
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

    public function stockMovements(Request $request): Response
    {
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $products = MasterProduct::query()->orderBy('name')->get(['id', 'sku', 'name']);

        $query = ProductStockMovement::query()
            ->with(['product', 'warehouse'])
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->integer('warehouse_id'));
        }
        if ($request->filled('product_id')) {
            $query->where('master_product_id', (int) $request->integer('product_id'));
        }
        if ($request->filled('type')) {
            $query->where('movement_type', $request->string('type')->toString());
        }
        if ($request->filled('from')) {
            $query->whereDate('movement_date', '>=', $request->string('from')->toString());
        }
        if ($request->filled('to')) {
            $query->whereDate('movement_date', '<=', $request->string('to')->toString());
        }
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($inner) use ($q) {
                $inner->where('note', 'like', '%'.$q.'%')
                    ->orWhereHas('product', fn ($p) => $p
                        ->where('sku', 'like', '%'.$q.'%')
                        ->orWhere('name', 'like', '%'.$q.'%'));
            });
        }

        $movements = $query->paginate(25)->withQueryString();

        return Inertia::render('ERP/Inventory/StockMovements', [
            'movements' => $movements->through(fn (ProductStockMovement $m) => [
                'id' => $m->id,
                'date' => $m->movement_date?->toDateString(),
                'type' => $m->movement_type,
                'sku' => $m->product?->sku,
                'product' => $m->product?->name,
                'warehouse' => $m->warehouse?->name ?? '-',
                'qty' => (float) $m->qty,
                'note' => $m->note,
            ]),
            'filters' => $request->only(['warehouse_id', 'product_id', 'type', 'from', 'to', 'q']),
            'warehouses' => $warehouses,
            'products' => $products,
            'types' => [
                'purchase_receipt',
                'pos_sale_out',
                'pos_refund_in',
                'pos_reopen_out',
                'in',
                'out',
                'opname_in',
                'opname_out',
                'manual_in',
                'manual_out',
            ],
        ]);
    }
}
