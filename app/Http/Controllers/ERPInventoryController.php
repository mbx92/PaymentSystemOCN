<?php

namespace App\Http\Controllers;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;
use App\Models\ProjectMaterial;
use App\Services\ProjectMaterialReservationService;
use App\Services\WarehouseStockRebuildService;
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
        $perPage = $this->resolvedPerPage($request);
        $lowStockOnly = $request->boolean('low_stock_only');
        $searchOperator = $this->caseInsensitiveLikeOperator();

        $query = MasterProduct::query()
            ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
            ->when($selectedWarehouseId && ! $request->filled('q'), function ($query) use ($selectedWarehouseId): void {
                $query->whereHas('warehouseStocks', function ($stock) use ($selectedWarehouseId): void {
                    $stock->where('warehouse_id', $selectedWarehouseId);
                });
            })
            ->when($request->filled('q'), function ($query) use ($request, $searchOperator): void {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term, $searchOperator): void {
                    $inner->where('sku', $searchOperator, '%'.$term.'%')
                        ->orWhere('name', $searchOperator, '%'.$term.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));

        if ($lowStockOnly && $selectedWarehouseId) {
            $query->where(function ($query) use ($selectedWarehouseId): void {
                $query
                    ->whereHas('warehouseStocks', function ($stock) use ($selectedWarehouseId): void {
                        $stock
                            ->where('warehouse_id', $selectedWarehouseId)
                            ->whereRaw('(qty - reserved_qty) <= master_products.min_stock');
                    })
                    ->orWhereDoesntHave('warehouseStocks', function ($stock) use ($selectedWarehouseId): void {
                        $stock->where('warehouse_id', $selectedWarehouseId);
                    });
            });
        }

        $paginator = $query
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $products = $paginator->through(function (MasterProduct $product) use ($selectedWarehouseId) {
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
                'description' => $product->description,
                'stock' => $qty,
                'reserved_qty' => $reservedQty,
                'available_qty' => $availableQty,
                'min_stock' => $product->min_stock,
                'low_stock_alert_enabled' => (bool) $product->low_stock_alert_enabled,
                'total_sold' => $product->total_sold,
                'status' => $product->status,
            ];
        });

        $idsOnPage = collect($products->items())->pluck('id')->all();
        $movementRowsByProduct = $selectedWarehouseId && count($idsOnPage) > 0
            ? ProductStockMovement::query()
                ->where('warehouse_id', $selectedWarehouseId)
                ->whereIn('master_product_id', $idsOnPage)
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->get(['id', 'master_product_id', 'movement_date', 'movement_type', 'qty', 'note'])
                ->groupBy('master_product_id')
                ->map(fn ($rows) => $rows
                    ->take(5)
                    ->map(fn (ProductStockMovement $movement) => [
                        'id' => $movement->id,
                        'date' => $movement->movement_date?->toDateString(),
                        'type' => $movement->movement_type,
                        'qty' => (float) $movement->qty,
                        'note' => $movement->note,
                    ])
                    ->values()
                    ->all())
                ->all()
            : [];
        $stockMismatch = $selectedWarehouseId
            ? app(WarehouseStockRebuildService::class)->mismatchSummary($selectedWarehouseId, $idsOnPage)
            : ['count' => 0, 'by_product' => []];

        $products = $products->through(function (array $product) use ($movementRowsByProduct, $stockMismatch) {
            $mismatch = $stockMismatch['by_product'][$product['id']] ?? null;
            $product['movement_mismatch'] = $mismatch !== null;
            $product['movement_expected_qty'] = $mismatch['expected_qty'] ?? $product['stock'];
            $product['movement_delta_qty'] = $mismatch['delta_qty'] ?? 0;
            $product['recent_movements'] = $movementRowsByProduct[$product['id']] ?? [];

            return $product;
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
                ->whereHas('project', fn ($query) => $query->whereIn('status', ['negosiasi', 'berjalan']))
                ->orderByDesc('reserved_qty')
                ->get()
                ->groupBy('master_product_id')
                ->map(function ($rows) {
                    return $rows
                        ->map(function (ProjectMaterial $material): array {
                            $outstanding = app(ProjectMaterialReservationService::class)->outstandingReservedQty($material);

                            return [
                                'project_id' => $material->project_id,
                                'project_name' => $material->project?->name,
                                'project_status' => $material->project?->status,
                                'planned_qty' => (float) $material->planned_qty,
                                'reserved_qty' => $outstanding,
                                'issued_qty' => (float) $material->issued_qty,
                            ];
                        })
                        ->filter(fn (array $row) => $row['reserved_qty'] > 0)
                        ->values();
                })
                ->only($idsOnPage);
        }

        return Inertia::render('ERP/Inventory/StockManagement', [
            'products' => $products,
            'warehouses' => $warehouses,
            'filters' => array_merge(
                $this->filtersWithPerPage($request, ['warehouse_id', 'q', 'status', 'low_stock_only']),
                [
                    'warehouse_id' => $selectedWarehouseId,
                    'low_stock_only' => $lowStockOnly,
                ],
            ),
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
            'batch_low_stock_alerts' => $this->stockProductsLowStockAlertBatchState(),
            'stock_movement_mismatch' => $stockMismatch,
        ]);
    }

    public function updateStock(Request $request, MasterProduct $masterProduct): RedirectResponse
    {
        $validated = $request->validate([
            'min_stock' => 'required|integer|min:0',
            'low_stock_alert_enabled' => 'required|boolean',
            'note' => 'nullable|string|max:255',
        ]);

        $masterProduct->update([
            'min_stock' => $validated['min_stock'],
            'low_stock_alert_enabled' => (bool) $validated['low_stock_alert_enabled'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Minimum stok berhasil diperbarui.']);
    }

    public function batchUpdateLowStockAlerts(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        MasterProduct::query()
            ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
            ->update(['low_stock_alert_enabled' => (bool) $validated['enabled']]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => (bool) $validated['enabled']
                ? 'Notifikasi stok rendah semua produk berhasil diaktifkan.'
                : 'Notifikasi stok rendah semua produk berhasil dinonaktifkan.',
        ]);
    }

    public function stockOpname(): Response
    {
        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $selectedWarehouseId = (int) request()->integer('warehouse_id', $warehouses->first()?->id ?? 0);
        $search = trim(request()->string('q')->toString());
        $searchOperator = $this->caseInsensitiveLikeOperator();

        return Inertia::render('ERP/Inventory/StockOpname', [
            'warehouses' => $warehouses,
            'filters' => [
                'warehouse_id' => $selectedWarehouseId ?: null,
                'q' => $search,
            ],
            'defaultStockOpnameDate' => now()->toDateString(),
            'products' => MasterProduct::query()
                ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
                ->where('status', 'active')
                ->when($selectedWarehouseId, function ($query) use ($selectedWarehouseId): void {
                    $query->where('warehouse_id', $selectedWarehouseId);
                })
                ->when($search !== '', function ($query) use ($search, $searchOperator): void {
                    $query->where(function ($inner) use ($search, $searchOperator): void {
                        $inner->where('sku', $searchOperator, '%'.$search.'%')
                            ->orWhere('name', $searchOperator, '%'.$search.'%');
                    });
                })
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'stock', 'uom'])
                ->map(function (MasterProduct $product) use ($selectedWarehouseId) {
                    $warehouseStock = $selectedWarehouseId
                        ? MasterProductWarehouseStock::query()
                            ->where('master_product_id', $product->id)
                            ->where('warehouse_id', $selectedWarehouseId)
                            ->first()
                        : null;

                    return [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'uom' => $product->uom,
                        'stock' => (float) $product->stock,
                        'warehouse_stock' => (float) ($warehouseStock?->qty ?? 0),
                        'reserved_qty' => (float) ($warehouseStock?->reserved_qty ?? 0),
                    ];
                })
                ->values(),
        ]);
    }

    public function storeStockOpname(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:master_products,id',
            'physical_stock' => 'required|numeric|min:0',
            'warehouse_id' => 'required|exists:warehouses,id',
            'stock_opname_date' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        $product = MasterProduct::query()->findOrFail($validated['product_id']);
        $newStock = (float) $validated['physical_stock'];

        DB::transaction(function () use ($product, $newStock, $validated): void {
            $warehouseStock = MasterProductWarehouseStock::query()->firstOrCreate(
                [
                    'master_product_id' => $product->id,
                    'warehouse_id' => $validated['warehouse_id'],
                ],
                [
                    'qty' => 0,
                    'reserved_qty' => 0,
                ]
            );

            $oldStock = (float) $warehouseStock->qty;
            $warehouseStock->update(['qty' => $newStock]);

            if ($newStock > $oldStock) {
                $this->allocateProjectMaterialReservations(
                    $product->id,
                    (int) $validated['warehouse_id'],
                    $newStock - $oldStock,
                );
            }

            app(ProjectMaterialReservationService::class)
                ->syncWarehouseReservation($product->id, (int) $validated['warehouse_id']);

            $totalStock = (float) MasterProductWarehouseStock::query()
                ->where('master_product_id', $product->id)
                ->sum('qty');

            $product->update(['stock' => (int) round($totalStock)]);

            $diff = $newStock - $oldStock;
            if ($diff !== 0) {
                ProductStockMovement::query()->create([
                    'master_product_id' => $product->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'movement_date' => $validated['stock_opname_date'],
                    'movement_type' => $diff > 0 ? 'opname_in' : 'opname_out',
                    'qty' => abs($diff),
                    'note' => $validated['note'] ?? 'Stock opname',
                ]);
            }
        });

        return redirect()
            ->route('erp.inventory.stock-opname', [
                'warehouse_id' => $validated['warehouse_id'],
            ])
            ->with('flash', ['type' => 'success', 'message' => 'Stock opname berhasil disimpan.']);
    }

    public function stockReport(Request $request): Response
    {
        $selectedYear = (int) $request->integer('year', now()->year);
        $selectedProductId = $request->filled('product_id') ? (int) $request->integer('product_id') : null;

        $products = MasterProduct::query()
            ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
            ->orderBy('name')
            ->get();

        $stockChart = $products->take(10)->map(fn (MasterProduct $item) => [
            'label' => $item->sku,
            'stock' => $item->stock,
        ])->values();

        $lowStockAlerts = $products
            ->filter(fn (MasterProduct $item) => $item->low_stock_alert_enabled && $item->stock <= $item->min_stock)
            ->values()
            ->map(fn (MasterProduct $item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'stock' => $item->stock,
                'min_stock' => $item->min_stock,
                'low_stock_alert_enabled' => (bool) $item->low_stock_alert_enabled,
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
                $targetStock = $item->min_stock;
                $suggestedQty = max($targetStock - $item->stock, 0);

                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'stock' => $item->stock,
                    'min_stock' => $item->min_stock,
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

    public function stockTransfer(): Response
    {
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        $products = MasterProduct::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'uom']);

        $warehouseStocks = MasterProductWarehouseStock::query()
            ->get(['master_product_id', 'warehouse_id', 'qty', 'reserved_qty'])
            ->groupBy('master_product_id')
            ->map(fn ($rows) => $rows->keyBy('warehouse_id')->map(fn ($r) => [
                'qty' => (float) $r->qty,
                'reserved' => (float) $r->reserved_qty,
                'available' => (float) $r->qty - (float) $r->reserved_qty,
            ]));

        return Inertia::render('ERP/Inventory/StockTransfer', [
            'warehouses' => $warehouses,
            'products' => $products,
            'warehouseStocks' => $warehouseStocks,
        ]);
    }

    public function storeStockTransfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:source_warehouse_id',
            'note' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:master_products,id',
            'items.*.qty' => 'required|numeric|gt:0',
        ]);

        $sourceWarehouse = Warehouse::query()->findOrFail($validated['source_warehouse_id']);
        $destWarehouse = Warehouse::query()->findOrFail($validated['destination_warehouse_id']);
        $note = trim((string) ($validated['note'] ?? ''));
        $transferNote = "Transfer {$sourceWarehouse->code} → {$destWarehouse->code}".($note !== '' ? ": {$note}" : '');

        $errors = [];
        foreach ($validated['items'] as $i => $item) {
            $product = MasterProduct::query()->find($item['product_id']);
            if (! $product) {
                continue;
            }
            $sourceStock = MasterProductWarehouseStock::query()
                ->where('master_product_id', $product->id)
                ->where('warehouse_id', $sourceWarehouse->id)
                ->first();
            $available = $sourceStock ? (float) $sourceStock->qty - (float) $sourceStock->reserved_qty : 0;
            if ((float) $item['qty'] > $available) {
                $errors["items.{$i}.qty"] = "{$product->sku}: stok tersedia hanya {$available}.";
            }
        }

        if (count($errors) > 0) {
            return back()->withErrors($errors)->withInput();
        }

        $transferred = 0;

        DB::transaction(function () use ($validated, $sourceWarehouse, $destWarehouse, $transferNote, &$transferred): void {
            $today = now()->toDateString();

            foreach ($validated['items'] as $item) {
                $product = MasterProduct::query()->find($item['product_id']);
                if (! $product) {
                    continue;
                }
                $qty = (float) $item['qty'];

                MasterProductWarehouseStock::query()
                    ->where('master_product_id', $product->id)
                    ->where('warehouse_id', $sourceWarehouse->id)
                    ->decrement('qty', $qty);

                $destinationStock = MasterProductWarehouseStock::query()->firstOrCreate(
                    ['master_product_id' => $product->id, 'warehouse_id' => $destWarehouse->id],
                    ['qty' => 0, 'reserved_qty' => 0]
                );
                $destinationStock->increment('qty', $qty);

                $this->allocateProjectMaterialReservations(
                    $product->id,
                    $destWarehouse->id,
                    $qty,
                );
                app(ProjectMaterialReservationService::class)
                    ->syncWarehouseReservation($product->id, $destWarehouse->id);

                ProductStockMovement::query()->create([
                    'master_product_id' => $product->id,
                    'warehouse_id' => $sourceWarehouse->id,
                    'movement_date' => $today,
                    'movement_type' => 'transfer_out',
                    'qty' => $qty,
                    'note' => $transferNote,
                ]);

                ProductStockMovement::query()->create([
                    'master_product_id' => $product->id,
                    'warehouse_id' => $destWarehouse->id,
                    'movement_date' => $today,
                    'movement_type' => 'transfer_in',
                    'qty' => $qty,
                    'note' => $transferNote,
                ]);

                $transferred++;
            }
        });

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Berhasil transfer {$transferred} produk dari {$sourceWarehouse->name} ke {$destWarehouse->name}.",
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

        $movements = $query->paginate($this->resolvedPerPage($request))->withQueryString();

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
            'filters' => $this->filtersWithPerPage($request, ['warehouse_id', 'product_id', 'type', 'from', 'to', 'q']),
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
                'transfer_in',
                'transfer_out',
            ],
        ]);
    }

    /**
     * @return 'all_on'|'all_off'|'mixed'
     */
    private function stockProductsLowStockAlertBatchState(): string
    {
        $base = MasterProduct::query()->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE);
        $total = (clone $base)->count();
        if ($total === 0) {
            return 'all_off';
        }

        $enabledCount = (clone $base)->where('low_stock_alert_enabled', true)->count();
        if ($enabledCount === $total) {
            return 'all_on';
        }
        if ($enabledCount === 0) {
            return 'all_off';
        }

        return 'mixed';
    }

    private function caseInsensitiveLikeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    private function allocateProjectMaterialReservations(int $productId, int $warehouseId, float $incomingQty): float
    {
        $remaining = $incomingQty;
        $allocated = 0.0;

        ProjectMaterial::query()
            ->with('project')
            ->where('master_product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['negosiasi', 'berjalan']))
            ->whereRaw('planned_qty > reserved_qty')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(function (ProjectMaterial $material) use (&$remaining, &$allocated): void {
                if ($remaining <= 0) {
                    return;
                }

                $shortage = max((float) $material->planned_qty - (float) $material->reserved_qty, 0);
                $toReserve = min($shortage, $remaining);
                if ($toReserve <= 0) {
                    return;
                }

                $material->reserved_qty = (float) $material->reserved_qty + $toReserve;
                $material->status = $this->projectMaterialStatus($material);
                $material->save();

                $remaining -= $toReserve;
                $allocated += $toReserve;
            });

        return $allocated;
    }

    private function projectMaterialStatus(ProjectMaterial $material): string
    {
        $plannedQty = (float) $material->planned_qty;
        $reservedQty = (float) $material->reserved_qty;
        $issuedQty = (float) $material->issued_qty;

        if ($plannedQty > 0 && $issuedQty >= $plannedQty) {
            return 'issued';
        }

        if ($plannedQty > 0 && $reservedQty >= $plannedQty) {
            return 'ready';
        }

        if ($reservedQty > 0) {
            return 'partial';
        }

        return 'planned';
    }
}
