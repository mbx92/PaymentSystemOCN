<?php

namespace App\Services;

use App\Models\MasterProductWarehouseStock;
use App\Models\Project;
use App\Models\ProjectMaterial;

class ProjectStockCheckService
{
    /**
     * @return array{
     *     project: array{
     *         id:string|int,
     *         name:string,
     *         status:string,
     *         started_at:?string,
     *         finished_at:?string
     *     },
     *     summary: array{
     *         line_count:int,
     *         warning_count:int,
     *         total_issued_qty:float,
     *         total_movement_net:float,
     *         total_warehouse_qty:float,
     *         mismatch_count:int
     *     },
     *     lines: list<array{
     *         material_id:int,
     *         sku:?string,
     *         name:?string,
     *         warehouse:?string,
     *         warehouse_id:?int,
     *         planned_qty:float,
     *         reserved_qty:float,
     *         issued_qty:float,
     *         movement_net:float,
     *         delta_qty:float,
     *         warehouse_qty:float,
     *         warehouse_reserved:float,
     *         master_stock:float,
     *         all_warehouse_qty:float,
     *         status:string,
     *         is_synced:bool
     *     }>,
     *     warnings: list<string>
     * }
     */
    public function inspect(Project $project): array
    {
        $project->loadMissing(['materials.product', 'materials.warehouse']);

        $materials = $project->materials
            ->filter(fn (ProjectMaterial $material) => $material->product?->isStockTracked() && (int) $material->warehouse_id > 0)
            ->values();

        $productIds = $materials->pluck('master_product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $warehouseStocks = MasterProductWarehouseStock::query()
            ->whereIn('master_product_id', $productIds)
            ->whereIn('warehouse_id', $materials->pluck('warehouse_id')->filter()->map(fn ($id) => (int) $id)->unique()->all())
            ->get()
            ->keyBy(fn (MasterProductWarehouseStock $row) => (int) $row->master_product_id.'-'.(int) $row->warehouse_id);

        $totalWarehouseStocks = MasterProductWarehouseStock::query()
            ->whereIn('master_product_id', $productIds)
            ->selectRaw('master_product_id, SUM(qty) as total_qty')
            ->groupBy('master_product_id')
            ->pluck('total_qty', 'master_product_id');

        $issueService = app(ProjectMaterialStockIssueService::class);
        $lines = [];
        $warnings = [];
        $mismatchCount = 0;

        foreach ($materials as $material) {
            $preview = $issueService->preview($material, (float) $material->issued_qty);
            $product = $material->product;
            $warehouseStock = $warehouseStocks->get((int) $material->master_product_id.'-'.(int) $material->warehouse_id);
            $warehouseQty = round((float) ($warehouseStock?->qty ?? 0), 2);
            $warehouseReserved = round((float) ($warehouseStock?->reserved_qty ?? 0), 2);
            $masterStock = round((float) ($product?->stock ?? 0), 2);
            $allWarehouseQty = round((float) ($totalWarehouseStocks[(int) $material->master_product_id] ?? 0), 2);
            $issuedQty = round((float) $material->issued_qty, 2);
            $movementNet = round((float) $preview['movement_net_before'], 2);
            $deltaQty = round($issuedQty - $movementNet, 2);
            $isSynced = abs($deltaQty) <= 0.00001;

            if (! $isSynced) {
                $mismatchCount++;
                $warnings[] = sprintf(
                    '[%s] issued %.2f tidak cocok dengan movement %.2f (delta %.2f).',
                    $product?->sku ?? ('Material #'.$material->id),
                    $issuedQty,
                    $movementNet,
                    $deltaQty,
                );
            }

            $lines[] = [
                'material_id' => (int) $material->id,
                'sku' => $product?->sku,
                'name' => $product?->name,
                'warehouse' => $material->warehouse?->name,
                'warehouse_id' => $material->warehouse_id ? (int) $material->warehouse_id : null,
                'planned_qty' => round((float) $material->planned_qty, 2),
                'reserved_qty' => round((float) $material->reserved_qty, 2),
                'issued_qty' => $issuedQty,
                'movement_net' => $movementNet,
                'delta_qty' => $deltaQty,
                'warehouse_qty' => $warehouseQty,
                'warehouse_reserved' => $warehouseReserved,
                'master_stock' => $masterStock,
                'all_warehouse_qty' => $allWarehouseQty,
                'status' => (string) $material->status,
                'is_synced' => $isSynced,
            ];
        }

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'started_at' => $project->started_at?->toDateString(),
                'finished_at' => $project->finished_at?->toDateString(),
            ],
            'summary' => [
                'line_count' => count($lines),
                'warning_count' => count($warnings),
                'total_issued_qty' => round(collect($lines)->sum('issued_qty'), 2),
                'total_movement_net' => round(collect($lines)->sum('movement_net'), 2),
                'total_warehouse_qty' => round(collect($lines)->sum('warehouse_qty'), 2),
                'mismatch_count' => $mismatchCount,
            ],
            'lines' => $lines,
            'warnings' => $warnings,
        ];
    }
}
