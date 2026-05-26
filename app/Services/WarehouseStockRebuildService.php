<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;

class WarehouseStockRebuildService
{
    private const IN_TYPES = [
        'purchase_receipt',
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
        'transfer_out',
    ];

    public function summarizeFromMovements(): array
    {
        return $this->scan(false);
    }

    public function rebuildFromMovements(): array
    {
        return $this->scan(true);
    }

    /**
     * @param  list<int|string>|null  $productIds
     */
    public function mismatchSummary(?int $warehouseId = null, ?array $productIds = null): array
    {
        $movementQuery = ProductStockMovement::query()
            ->selectRaw('master_product_id, warehouse_id')
            ->selectRaw(
                "SUM(CASE
                    WHEN movement_type IN ('purchase_receipt','pos_refund_in','in','opname_in','manual_in','transfer_in') THEN qty
                    WHEN movement_type IN ('project_issue_out','pos_sale_out','pos_reopen_out','out','opname_out','manual_out','transfer_out') THEN -qty
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
            ->get(['master_product_id', 'warehouse_id', 'qty', 'reserved_qty']);

        $checked = 0;
        $updated = 0;
        $created = 0;
        $totalBefore = 0.0;
        $totalAfter = 0.0;
        $productIds = [];

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
        }

        if ($apply && count($productIds) > 0) {
            $this->syncMasterProductTotals(array_keys($productIds));
        }

        return [
            'warehouse_rows_checked' => $checked,
            'warehouse_rows_updated' => $updated,
            'warehouse_rows_created' => $created,
            'total_qty_before' => round($totalBefore, 2),
            'total_qty_after' => round($totalAfter, 2),
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
