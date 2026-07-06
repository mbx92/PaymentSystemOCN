<?php

namespace App\Services;

use App\ERP\Purchasing\Models\GoodsReceipt;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;

class GoodsReceiptStockCheckService
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
        'purchase_reopen_out',
        'transfer_out',
    ];

    /**
     * @return array{
     *     receipt: array{
     *         number: string,
     *         status: string,
     *         received_date: string|null,
     *         purchase_order: string|null,
     *         warehouse_id: int|null,
     *         warehouse_name: string|null
     *     },
     *     summary: array{
     *         line_count: int,
     *         warning_count: int,
     *         total_gr_qty: float,
     *         total_gr_net: float,
     *         total_warehouse_qty: float
     *     },
     *     lines: list<array{
     *         sku: string|null,
     *         name: string|null,
     *         gr_qty: float,
     *         status_expected_net: float,
     *         gr_in: float,
     *         gr_reopen_out: float,
     *         gr_net: float,
     *         warehouse_qty: float,
     *         warehouse_reserved: float,
     *         all_movement_expected: float,
     *         master_stock: float,
     *         all_warehouse_qty: float,
     *         po_received_qty: float
     *     }>,
     *     warnings: list<string>
     * }
     */
    public function inspect(GoodsReceipt $receipt): array
    {
        $receipt->loadMissing([
            'purchaseOrder.lines',
            'warehouse',
            'lines.product.warehouseStocks',
        ]);

        $productIds = $receipt->lines
            ->pluck('master_product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $warehouseId = $receipt->warehouse_id ? (int) $receipt->warehouse_id : null;

        $warehouseStocks = MasterProductWarehouseStock::query()
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->whereIn('master_product_id', $productIds)
            ->get()
            ->keyBy(fn (MasterProductWarehouseStock $row) => (int) $row->master_product_id);

        $totalWarehouseStocks = MasterProductWarehouseStock::query()
            ->whereIn('master_product_id', $productIds)
            ->selectRaw('master_product_id, SUM(qty) as total_qty')
            ->groupBy('master_product_id')
            ->pluck('total_qty', 'master_product_id');

        $grMovementRows = ProductStockMovement::query()
            ->whereIn('master_product_id', $productIds)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->where(function ($query) use ($receipt): void {
                $query->where('note', 'Receipt '.$receipt->number)
                    ->orWhere('note', 'Reopen receipt '.$receipt->number);
            })
            ->get();

        $allMovementRows = ProductStockMovement::query()
            ->whereIn('master_product_id', $productIds)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->get();

        $poLines = $receipt->purchaseOrder?->lines
            ?->keyBy(fn ($line) => (int) $line->master_product_id) ?? collect();

        $lineRows = [];
        $warnings = [];

        foreach ($receipt->lines as $line) {
            $product = $line->product;
            if (! $product) {
                continue;
            }

            $productId = (int) $product->id;
            $qtyReceived = round((float) $line->qty_received, 2);
            $warehouseStock = $warehouseStocks->get($productId);

            $grIn = round((float) $grMovementRows
                ->where('master_product_id', $productId)
                ->where('movement_type', 'purchase_receipt')
                ->sum('qty'), 2);

            $grOut = round((float) $grMovementRows
                ->where('master_product_id', $productId)
                ->where('movement_type', 'purchase_reopen_out')
                ->sum('qty'), 2);

            $grNet = round($grIn - $grOut, 2);
            $expectedGrNet = $receipt->status->value === 'posted' ? $qtyReceived : 0.0;

            $allMovementExpected = round($this->signedMovementQty(
                $allMovementRows->where('master_product_id', $productId)->all()
            ), 2);

            $warehouseQty = round((float) ($warehouseStock?->qty ?? 0), 2);
            $warehouseReserved = round((float) ($warehouseStock?->reserved_qty ?? 0), 2);
            $masterStock = round((float) $product->stock, 2);
            $totalWarehouseQty = round((float) ($totalWarehouseStocks[$productId] ?? 0), 2);
            $poReceivedQty = round((float) ($poLines->get($productId)?->received_qty ?? 0), 2);

            if (abs($grNet - $expectedGrNet) > 0.00001) {
                $warnings[] = sprintf(
                    '[%s] net movement GR %.2f tidak cocok dengan status %s (expected %.2f).',
                    $product->sku ?? $product->name,
                    $grNet,
                    $receipt->status->value,
                    $expectedGrNet,
                );
            }

            if (abs($warehouseQty - $allMovementExpected) > 0.00001) {
                $warnings[] = sprintf(
                    '[%s] qty warehouse %.2f tidak cocok dengan total movement %.2f.',
                    $product->sku ?? $product->name,
                    $warehouseQty,
                    $allMovementExpected,
                );
            }

            if (abs($masterStock - $totalWarehouseQty) > 0.00001) {
                $warnings[] = sprintf(
                    '[%s] master stock %.2f tidak cocok dengan total semua warehouse %.2f.',
                    $product->sku ?? $product->name,
                    $masterStock,
                    $totalWarehouseQty,
                );
            }

            $lineRows[] = [
                'sku' => $product->sku,
                'name' => $product->name,
                'gr_qty' => $qtyReceived,
                'status_expected_net' => $expectedGrNet,
                'gr_in' => $grIn,
                'gr_reopen_out' => $grOut,
                'gr_net' => $grNet,
                'warehouse_qty' => $warehouseQty,
                'warehouse_reserved' => $warehouseReserved,
                'all_movement_expected' => $allMovementExpected,
                'master_stock' => $masterStock,
                'all_warehouse_qty' => $totalWarehouseQty,
                'po_received_qty' => $poReceivedQty,
            ];
        }

        return [
            'receipt' => [
                'number' => $receipt->number,
                'status' => $receipt->status->value,
                'received_date' => $receipt->received_date?->toDateString(),
                'purchase_order' => $receipt->purchaseOrder?->number,
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $receipt->warehouse?->name ?? $receipt->warehouse_name,
            ],
            'summary' => [
                'line_count' => count($lineRows),
                'warning_count' => count($warnings),
                'total_gr_qty' => round(collect($lineRows)->sum('gr_qty'), 2),
                'total_gr_net' => round(collect($lineRows)->sum('gr_net'), 2),
                'total_warehouse_qty' => round(collect($lineRows)->sum('warehouse_qty'), 2),
            ],
            'lines' => $lineRows,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, ProductStockMovement>  $rows
     */
    private function signedMovementQty(array $rows): float
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $qty = (float) $row->qty;

            if (in_array($row->movement_type, self::IN_TYPES, true)) {
                $total += $qty;
                continue;
            }

            if (in_array($row->movement_type, self::OUT_TYPES, true)) {
                $total -= $qty;
            }
        }

        return $total;
    }
}
