<?php

namespace App\Services;

use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;
use App\Models\ProjectMaterial;
use Illuminate\Validation\ValidationException;

class ProjectMaterialStockIssueService
{
    public const ISSUE_OUT_TYPE = 'project_issue_out';

    public const ISSUE_RETURN_IN_TYPE = 'project_issue_return_in';

    /**
     * @return array{
     *     material_id:int,
     *     project_id:string|int|null,
     *     sku:string|null,
     *     warehouse_id:int|null,
     *     target_issued_qty:float,
     *     movement_net_before:float,
     *     delta_qty:float,
     *     warehouse_qty_before:float,
     *     warehouse_qty_after:float,
     *     action:string
     * }
     */
    public function preview(ProjectMaterial $material, ?float $targetIssuedQty = null): array
    {
        $material->loadMissing(['project', 'product']);

        $target = $this->normalizeTargetIssuedQty($material, $targetIssuedQty);
        $movementNet = $this->projectMovementNet($material);
        $warehouseQty = $this->warehouseQty($material);
        $delta = round($target - $movementNet, 2);

        return [
            'material_id' => (int) $material->id,
            'project_id' => $material->project_id,
            'sku' => $material->product?->sku,
            'warehouse_id' => $material->warehouse_id ? (int) $material->warehouse_id : null,
            'target_issued_qty' => $target,
            'movement_net_before' => $movementNet,
            'delta_qty' => $delta,
            'warehouse_qty_before' => $warehouseQty,
            'warehouse_qty_after' => round($warehouseQty - $delta, 2),
            'action' => $delta > 0 ? 'issue_out' : ($delta < 0 ? 'return_in' : 'noop'),
        ];
    }

    /**
     * @return array{
     *     material_id:int,
     *     project_id:string|int|null,
     *     sku:string|null,
     *     warehouse_id:int|null,
     *     target_issued_qty:float,
     *     movement_net_before:float,
     *     delta_qty:float,
     *     warehouse_qty_before:float,
     *     warehouse_qty_after:float,
     *     action:string
     * }
     */
    public function sync(ProjectMaterial $material, ?float $targetIssuedQty = null, ?string $movementDate = null): array
    {
        $material = ProjectMaterial::query()
            ->with(['project', 'product'])
            ->lockForUpdate()
            ->findOrFail($material->id);

        $product = $material->product;
        if (! $product) {
            throw ValidationException::withMessages([
                'used' => 'Produk material tidak ditemukan.',
            ]);
        }

        $target = $this->normalizeTargetIssuedQty($material, $targetIssuedQty);
        $movementNet = $this->projectMovementNet($material);
        $delta = round($target - $movementNet, 2);
        $warehouseQtyBefore = $this->warehouseQty($material);
        $warehouseQtyAfter = $warehouseQtyBefore;
        $action = 'noop';

        if ($product->isStockTracked() && (int) $material->warehouse_id > 0 && abs($delta) > 0.00001) {
            $stockRow = MasterProductWarehouseStock::query()->lockForUpdate()->firstOrCreate(
                [
                    'master_product_id' => (int) $material->master_product_id,
                    'warehouse_id' => (int) $material->warehouse_id,
                ],
                ['qty' => 0, 'reserved_qty' => 0],
            );

            $warehouseQtyBefore = round((float) $stockRow->qty, 2);

            if ($delta > 0) {
                if ((float) $stockRow->qty + 0.00001 < $delta) {
                    throw ValidationException::withMessages([
                        'used' => 'Stock gudang tidak cukup untuk menandai material sebagai terpakai.',
                    ]);
                }

                $stockRow->decrement('qty', $delta);
                $this->createMovement($material, self::ISSUE_OUT_TYPE, $delta, $movementDate);
                $action = 'issue_out';
            } else {
                $returnQty = abs($delta);
                $stockRow->increment('qty', $returnQty);
                $this->createMovement($material, self::ISSUE_RETURN_IN_TYPE, $returnQty, $movementDate);
                $action = 'return_in';
            }

            $warehouseQtyAfter = round((float) $stockRow->fresh()->qty, 2);
        }

        $material->issued_qty = $target;
        $material->status = $this->projectMaterialStatus($material, $product);
        $material->save();

        if ($product->isStockTracked() && (int) $material->warehouse_id > 0) {
            app(ProjectMaterialReservationService::class)
                ->syncWarehouseReservation((int) $material->master_product_id, (int) $material->warehouse_id);
        }

        return [
            'material_id' => (int) $material->id,
            'project_id' => $material->project_id,
            'sku' => $product->sku,
            'warehouse_id' => $material->warehouse_id ? (int) $material->warehouse_id : null,
            'target_issued_qty' => $target,
            'movement_net_before' => $movementNet,
            'delta_qty' => $delta,
            'warehouse_qty_before' => $warehouseQtyBefore,
            'warehouse_qty_after' => $warehouseQtyAfter,
            'action' => $action,
        ];
    }

    public function projectMovementNet(ProjectMaterial $material): float
    {
        if ((int) $material->master_product_id <= 0 || (int) $material->warehouse_id <= 0) {
            return 0.0;
        }

        $rows = ProductStockMovement::query()
            ->where('master_product_id', (int) $material->master_product_id)
            ->where('warehouse_id', (int) $material->warehouse_id)
            ->where(function ($query) use ($material): void {
                $query
                    ->where(function ($inner) use ($material): void {
                        $inner
                            ->where('movement_type', self::ISSUE_OUT_TYPE)
                            ->where('note', 'like', $this->issueNotePrefix($material).'%');
                    })
                    ->orWhere(function ($inner) use ($material): void {
                        $inner
                            ->where('movement_type', self::ISSUE_RETURN_IN_TYPE)
                            ->where('note', 'like', $this->issueReturnNotePrefix($material).'%');
                    });
            })
            ->get(['movement_type', 'qty']);

        $issued = (float) $rows->where('movement_type', self::ISSUE_OUT_TYPE)->sum('qty');
        $returned = (float) $rows->where('movement_type', self::ISSUE_RETURN_IN_TYPE)->sum('qty');

        return round($issued - $returned, 2);
    }

    private function normalizeTargetIssuedQty(ProjectMaterial $material, ?float $targetIssuedQty): float
    {
        $plannedQty = max((float) $material->planned_qty, 0);
        $target = $targetIssuedQty ?? (float) $material->issued_qty;
        $target = max($target, 0);

        return round(min($target, $plannedQty), 2);
    }

    private function warehouseQty(ProjectMaterial $material): float
    {
        if ((int) $material->master_product_id <= 0 || (int) $material->warehouse_id <= 0) {
            return 0.0;
        }

        $row = MasterProductWarehouseStock::query()
            ->where('master_product_id', (int) $material->master_product_id)
            ->where('warehouse_id', (int) $material->warehouse_id)
            ->first(['qty']);

        return round((float) ($row?->qty ?? 0), 2);
    }

    private function createMovement(ProjectMaterial $material, string $movementType, float $qty, ?string $movementDate = null): void
    {
        ProductStockMovement::query()->create([
            'master_product_id' => (int) $material->master_product_id,
            'warehouse_id' => (int) $material->warehouse_id,
            'movement_date' => $movementDate ?: $this->resolveMovementDate($material),
            'movement_type' => $movementType,
            'qty' => $qty,
            'note' => $movementType === self::ISSUE_OUT_TYPE
                ? $this->issueNote($material)
                : $this->issueReturnNote($material),
        ]);
    }

    private function resolveMovementDate(ProjectMaterial $material): string
    {
        $project = $material->project;

        return $project?->finished_at?->toDateString()
            ?? $project?->started_at?->toDateString()
            ?? now()->toDateString();
    }

    private function issueNote(ProjectMaterial $material): string
    {
        return $this->issueNotePrefix($material).' - '.($material->project?->name ?? 'Project');
    }

    private function issueReturnNote(ProjectMaterial $material): string
    {
        return $this->issueReturnNotePrefix($material).' - '.($material->project?->name ?? 'Project');
    }

    private function issueNotePrefix(ProjectMaterial $material): string
    {
        return 'Project issue '.$material->project_id.' material '.$material->id;
    }

    private function issueReturnNotePrefix(ProjectMaterial $material): string
    {
        return 'Project issue return '.$material->project_id.' material '.$material->id;
    }

    private function projectMaterialStatus(ProjectMaterial $material, $product): string
    {
        $plannedQty = (float) $material->planned_qty;
        $reservedQty = (float) $material->reserved_qty;
        $issuedQty = (float) $material->issued_qty;

        if (! $product->isStockTracked()) {
            if ($plannedQty > 0 && $issuedQty >= $plannedQty) {
                return 'issued';
            }

            return 'ready';
        }

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
