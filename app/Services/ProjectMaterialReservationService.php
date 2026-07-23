<?php

namespace App\Services;

use App\Models\MasterProductWarehouseStock;
use App\Models\Project;
use App\Models\ProjectMaterial;

class ProjectMaterialReservationService
{
    public function summarizeAllWarehouseReservations(): array
    {
        return $this->scanAllWarehouseReservations(false);
    }

    public function syncAllWarehouseReservations(): array
    {
        return $this->scanAllWarehouseReservations(true);
    }

    public function syncWarehouseReservation(int $productId, int $warehouseId): MasterProductWarehouseStock
    {
        $stock = MasterProductWarehouseStock::query()->firstOrCreate(
            [
                'master_product_id' => $productId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'qty' => 0,
                'reserved_qty' => 0,
            ],
        );

        $reservedQty = (float) ProjectMaterial::query()
            ->where('master_product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereHas('project', fn ($query) => $query->whereIn('status', ['negosiasi', 'berjalan']))
            ->get(['reserved_qty', 'issued_qty'])
            ->sum(fn (ProjectMaterial $material) => $this->outstandingReservedQty($material));

        $stock->update([
            'reserved_qty' => number_format($reservedQty, 2, '.', ''),
        ]);

        return $stock->fresh();
    }

    /**
     * Release warehouse reservation for a finished/cancelled project.
     * Also clears unissued reserved_qty on the project material rows so UI no longer shows stale reserved.
     */
    public function releaseProjectReservations(Project $project): void
    {
        $materials = $project->materials()
            ->whereNotNull('warehouse_id')
            ->get();

        foreach ($materials as $material) {
            $issuedQty = (float) $material->issued_qty;
            $reservedQty = (float) $material->reserved_qty;

            if ($reservedQty > $issuedQty + 0.00001) {
                $material->reserved_qty = $issuedQty;
                $material->status = $this->resolveMaterialStatus($material);
                $material->save();
            }
        }

        $this->syncProjectWarehouseReservations($project);
    }

    /**
     * Recompute warehouse reserved_qty for all product/warehouse pairs on the project.
     */
    public function syncProjectWarehouseReservations(Project $project): void
    {
        $pairs = $project->materials()
            ->select(['master_product_id', 'warehouse_id'])
            ->whereNotNull('warehouse_id')
            ->get()
            ->unique(fn (ProjectMaterial $material) => $material->master_product_id.'-'.$material->warehouse_id);

        foreach ($pairs as $material) {
            $this->syncWarehouseReservation((int) $material->master_product_id, (int) $material->warehouse_id);
        }
    }

    /**
     * Repair all selesai/dibatalkan projects that still hold unissued reserved_qty.
     *
     * @return array{projects_touched: int, materials_cleared: int}
     */
    public function repairClosedProjectReservations(): array
    {
        $projects = Project::query()
            ->whereIn('status', ['selesai', 'dibatalkan'])
            ->whereHas('materials', fn ($q) => $q->whereColumn('reserved_qty', '>', 'issued_qty'))
            ->with('materials')
            ->get();

        $materialsCleared = 0;

        foreach ($projects as $project) {
            $before = $project->materials
                ->filter(fn (ProjectMaterial $m) => (float) $m->reserved_qty > (float) $m->issued_qty + 0.00001)
                ->count();

            $this->releaseProjectReservations($project);
            $materialsCleared += $before;
        }

        return [
            'projects_touched' => $projects->count(),
            'materials_cleared' => $materialsCleared,
        ];
    }

    public function outstandingReservedQty(ProjectMaterial $material): float
    {
        return max((float) $material->reserved_qty - (float) $material->issued_qty, 0);
    }

    private function resolveMaterialStatus(ProjectMaterial $material): string
    {
        $plannedQty = (float) $material->planned_qty;
        $reservedQty = (float) $material->reserved_qty;
        $issuedQty = (float) $material->issued_qty;

        if ($plannedQty > 0 && $issuedQty >= $plannedQty) {
            return 'issued';
        }

        if ($issuedQty > 0) {
            return 'partial';
        }

        if ($plannedQty > 0 && $reservedQty >= $plannedQty) {
            return 'ready';
        }

        if ($reservedQty > 0) {
            return 'partial';
        }

        return 'planned';
    }

    private function scanAllWarehouseReservations(bool $apply): array
    {
        $rows = MasterProductWarehouseStock::query()
            ->get(['id', 'master_product_id', 'warehouse_id', 'reserved_qty']);

        $updated = 0;
        $cleared = 0;
        $changedProducts = [];
        $totalBefore = 0.0;
        $totalAfter = 0.0;

        foreach ($rows as $row) {
            $before = (float) $row->reserved_qty;
            $expected = (float) ProjectMaterial::query()
                ->where('master_product_id', $row->master_product_id)
                ->where('warehouse_id', $row->warehouse_id)
                ->whereHas('project', fn ($query) => $query->whereIn('status', ['negosiasi', 'berjalan']))
                ->get(['reserved_qty', 'issued_qty'])
                ->sum(fn (ProjectMaterial $material) => $this->outstandingReservedQty($material));

            $totalBefore += $before;
            $totalAfter += $expected;

            if (abs($before - $expected) > 0.00001) {
                $updated++;
                if ($before > 0 && $expected <= 0.00001) {
                    $cleared++;
                }
                $changedProducts[$row->master_product_id.'-'.$row->warehouse_id] = true;

                if ($apply) {
                    $row->update([
                        'reserved_qty' => number_format($expected, 2, '.', ''),
                    ]);
                }
            }
        }

        return [
            'warehouse_rows_checked' => $rows->count(),
            'warehouse_rows_updated' => $updated,
            'warehouse_rows_cleared' => $cleared,
            'product_warehouse_pairs_changed' => count($changedProducts),
            'total_reserved_before' => round($totalBefore, 2),
            'total_reserved_after' => round($totalAfter, 2),
        ];
    }
}
