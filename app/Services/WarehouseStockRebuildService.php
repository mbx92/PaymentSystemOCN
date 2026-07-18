<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarehouseStockRebuildService
{
    private const IN_TYPES = [
        'purchase_receipt',
        'project_issue_return_in',
        'pos_refund_in',
        'in',
        'opname_in',
        'manual_in',
        'transfer_in',
    ];

    private const OUT_TYPES = [
        'project_issue_out',
        'pos_sale_out',
        'pos_reopen_out',
        'out',
        'opname_out',
        'manual_out',
        'purchase_reopen_out',
        'transfer_out',
    ];

    public function summarizeFromMovements(): array
    {
        return $this->scan(false);
    }

    public function rebuildFromMovements(): array
    {
        return DB::transaction(function (): array {
            $result = $this->scan(true);

            $reservationResult = app(ProjectMaterialReservationService::class)->syncAllWarehouseReservations();
            $result['reservation_rows_updated'] = $reservationResult['warehouse_rows_updated'] ?? 0;
            $result['reservation_rows_cleared'] = $reservationResult['warehouse_rows_cleared'] ?? 0;

            return $result;
        });
    }

    /**
     * @param  list<int|string>|null  $productIds
     */
    public function mismatchSummary(?int $warehouseId = null, ?array $productIds = null): array
    {
        $cacheKey = 'mismatch_summary_'.($warehouseId ?? 'all').'_'.(($productIds !== null) ? md5(implode(',', $productIds)) : 'all');
        $cacheTtl = now()->addSeconds(30);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($warehouseId, $productIds) {
            return $this->computeMismatchSummary($warehouseId, $productIds);
        });
    }

    /**
     * @return list<array{sku: string|null, product_name: string|null, warehouse_id: int, actual_qty: float, expected_qty: float, delta_qty: float}>
     */
    public function mismatchSamples(int $limit = 30): array
    {
        $scan = $this->scan(false);
        $mismatches = $scan['mismatches'] ?? [];

        if ($mismatches === []) {
            return [];
        }

        $productIds = collect($mismatches)->pluck('master_product_id')->unique()->values()->all();
        $products = MasterProduct::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'sku', 'name'])
            ->keyBy('id');

        return collect($mismatches)
            ->take($limit)
            ->map(function (array $row) use ($products): array {
                $product = $products->get($row['master_product_id']);

                return [
                    'sku' => $product?->sku,
                    'product_name' => $product?->name,
                    'warehouse_id' => $row['warehouse_id'],
                    'actual_qty' => $row['actual_qty'],
                    'expected_qty' => $row['expected_qty'],
                    'delta_qty' => $row['delta_qty'],
                ];
            })
            ->values()
            ->all();
    }

    private function computeMismatchSummary(?int $warehouseId = null, ?array $productIds = null): array
    {
        $movementQuery = ProductStockMovement::query()
            ->selectRaw('master_product_id, warehouse_id')
            ->selectRaw(
                "SUM(CASE
                    WHEN movement_type IN ('purchase_receipt','project_issue_return_in','pos_refund_in','in','opname_in','manual_in','transfer_in') THEN qty
                    WHEN movement_type IN ('project_issue_out','pos_sale_out','pos_reopen_out','out','opname_out','manual_out','purchase_reopen_out','transfer_out') THEN -qty
                    ELSE 0
                END) as expected_qty"
            )
            ->whereNotNull('warehouse_id')
            ->groupBy('master_product_id', 'warehouse_id');

        if ($warehouseId) {
            $movementQuery->where('warehouse_id', $warehouseId);
        }
        if ($productIds !== null && count($productIds) > 0) {
            $movementQuery->whereIn('master_product_id', $productIds);
        }

        $movementRows = $movementQuery->get();
        $expectedMap = [];
        foreach ($movementRows as $row) {
            $expectedMap[(int) $row->master_product_id.'-'.(int) $row->warehouse_id] = round(max((float) $row->expected_qty, 0), 2);
        }

        $stockQuery = MasterProductWarehouseStock::query()
            ->select(['master_product_id', 'warehouse_id', 'qty']);
        if ($warehouseId) {
            $stockQuery->where('warehouse_id', $warehouseId);
        }
        if ($productIds !== null && count($productIds) > 0) {
            $stockQuery->whereIn('master_product_id', $productIds);
        }

        $stockRows = $stockQuery->get();
        $mismatchesByProduct = [];
        $count = 0;

        foreach ($stockRows as $row) {
            $key = (int) $row->master_product_id.'-'.(int) $row->warehouse_id;
            $actual = round((float) $row->qty, 2);
            $expected = (float) ($expectedMap[$key] ?? 0);
            if (abs($actual - $expected) <= 0.00001) {
                continue;
            }

            $count++;
            $mismatchesByProduct[(int) $row->master_product_id] = [
                'actual_qty' => $actual,
                'expected_qty' => $expected,
                'delta_qty' => round($expected - $actual, 2),
            ];
        }

        return [
            'count' => $count,
            'by_product' => $mismatchesByProduct,
        ];
    }

    private function scan(bool $apply): array
    {
        $movementRows = ProductStockMovement::query()
            ->get(['master_product_id', 'warehouse_id', 'movement_type', 'qty']);

        $expectedByPair = [];
        foreach ($movementRows as $row) {
            $productId = (int) $row->master_product_id;
            $warehouseId = (int) $row->warehouse_id;
            if ($productId <= 0 || $warehouseId <= 0) {
                continue;
            }

            $pairKey = $productId.'-'.$warehouseId;
            $expectedByPair[$pairKey] ??= [
                'master_product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'qty' => 0.0,
            ];

            $qty = (float) $row->qty;
            if (in_array($row->movement_type, self::IN_TYPES, true)) {
                $expectedByPair[$pairKey]['qty'] += $qty;
            } elseif (in_array($row->movement_type, self::OUT_TYPES, true)) {
                $expectedByPair[$pairKey]['qty'] -= $qty;
            }
        }

        foreach ($expectedByPair as &$pair) {
            $pair['qty'] = max(round((float) $pair['qty'], 2), 0);
        }
        unset($pair);

        $existingRows = MasterProductWarehouseStock::query()
            ->get(['id', 'master_product_id', 'warehouse_id', 'qty', 'reserved_qty']);

        $checked = 0;
        $updated = 0;
        $created = 0;
        $zeroed = 0;
        $totalBefore = 0.0;
        $totalAfter = 0.0;
        $productIds = [];
        $mismatches = [];

        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[(int) $row->master_product_id.'-'.(int) $row->warehouse_id] = $row;
        }

        foreach ($expectedByPair as $pairKey => $expected) {
            $checked++;
            $current = $existingMap[$pairKey] ?? null;
            $before = (float) ($current?->qty ?? 0);
            $after = (float) $expected['qty'];
            $totalBefore += $before;
            $totalAfter += $after;

            if (abs($before - $after) > 0.00001) {
                $updated++;
                $productIds[$expected['master_product_id']] = true;
                $mismatches[] = [
                    'master_product_id' => $expected['master_product_id'],
                    'warehouse_id' => $expected['warehouse_id'],
                    'actual_qty' => $before,
                    'expected_qty' => $after,
                    'delta_qty' => round($after - $before, 2),
                ];

                if ($apply) {
                    if ($current) {
                        $current->update(['qty' => number_format($after, 2, '.', '')]);
                    } else {
                        MasterProductWarehouseStock::query()->create([
                            'master_product_id' => $expected['master_product_id'],
                            'warehouse_id' => $expected['warehouse_id'],
                            'qty' => number_format($after, 2, '.', ''),
                            'reserved_qty' => 0,
                        ]);
                        $created++;
                    }
                }
            }

            unset($existingMap[$pairKey]);
        }

        // Rows with stock but no movement history must be zeroed for validity.
        foreach ($existingMap as $orphan) {
            $before = (float) $orphan->qty;
            $checked++;
            $totalBefore += $before;
            $totalAfter += 0;

            if (abs($before) > 0.00001) {
                $updated++;
                $zeroed++;
                $productIds[(int) $orphan->master_product_id] = true;
                $mismatches[] = [
                    'master_product_id' => (int) $orphan->master_product_id,
                    'warehouse_id' => (int) $orphan->warehouse_id,
                    'actual_qty' => $before,
                    'expected_qty' => 0.0,
                    'delta_qty' => round(0 - $before, 2),
                ];

                if ($apply) {
                    $orphan->update(['qty' => '0.00']);
                }
            }
        }

        if ($apply && count($productIds) > 0) {
            $this->syncMasterProductTotals(array_keys($productIds));
        }

        return [
            'warehouse_rows_checked' => $checked,
            'warehouse_rows_updated' => $updated,
            'warehouse_rows_created' => $created,
            'warehouse_rows_zeroed' => $zeroed,
            'total_qty_before' => round($totalBefore, 2),
            'total_qty_after' => round($totalAfter, 2),
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @param  array<int, int|string>  $productIds
     */
    private function syncMasterProductTotals(array $productIds): void
    {
        $totals = MasterProductWarehouseStock::query()
            ->whereIn('master_product_id', $productIds)
            ->selectRaw('master_product_id, SUM(qty) as total_qty')
            ->groupBy('master_product_id')
            ->pluck('total_qty', 'master_product_id');

        $products = MasterProduct::query()->whereIn('id', $productIds)->get(['id', 'stock']);
        foreach ($products as $product) {
            $product->update([
                'stock' => (int) round((float) ($totals[$product->id] ?? 0)),
            ]);
        }
    }
}
