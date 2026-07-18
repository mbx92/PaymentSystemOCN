<?php

namespace App\Console\Commands;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Services\ProjectMaterialReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairClosedProjectMaterialReservationsCommand extends Command
{
    protected $signature = 'projects:repair-closed-material-reservations
        {--move-camhlk007 : Pindahkan baris CAMHLK007 yang masih di WH-NUMA (belum issued) ke WH-OCN}
        {--dry-run : Hanya tampilkan ringkasan tanpa menulis ke database}';

    protected $description = 'Clear stale reserved_qty on selesai/dibatalkan project materials and resync warehouse reserved stock';

    public function handle(ProjectMaterialReservationService $reservationService): int
    {
        if ($this->option('dry-run')) {
            $count = Project::query()
                ->whereIn('status', ['selesai', 'dibatalkan'])
                ->whereHas('materials', fn ($q) => $q->whereColumn('reserved_qty', '>', 'issued_qty'))
                ->count();
            $this->info("Dry-run: {$count} closed project(s) still have stale reserved_qty.");

            if ($this->option('move-camhlk007')) {
                $this->moveCamhlk007ToWhOcn($reservationService, true);
            }

            return self::SUCCESS;
        }

        $result = $reservationService->repairClosedProjectReservations();

        $this->info(sprintf(
            'Closed projects repaired: %d (materials cleared: %d)',
            $result['projects_touched'],
            $result['materials_cleared']
        ));

        if ($this->option('move-camhlk007')) {
            $this->moveCamhlk007ToWhOcn($reservationService, false);
        }

        return self::SUCCESS;
    }

    private function moveCamhlk007ToWhOcn(ProjectMaterialReservationService $reservationService, bool $dryRun): void
    {
        $product = MasterProduct::query()->where('sku', 'CAMHLK007')->first();
        $whOcn = Warehouse::query()->where('code', 'WH-OCN')->where('is_active', true)->first();
        $whNuma = Warehouse::query()->where('code', 'WH-NUMA')->where('is_active', true)->first();

        if (! $product || ! $whOcn || ! $whNuma) {
            $this->warn('CAMHLK007 / WH-OCN / WH-NUMA tidak lengkap — skip pindah gudang.');

            return;
        }

        $numaMaterials = ProjectMaterial::query()
            ->with('project:id,name,status')
            ->where('master_product_id', $product->id)
            ->where('warehouse_id', $whNuma->id)
            ->where('issued_qty', '<=', 0)
            ->get();

        if ($numaMaterials->isEmpty()) {
            $this->info('Tidak ada baris CAMHLK007 di WH-NUMA yang perlu dipindah.');

            return;
        }

        foreach ($numaMaterials as $numaMaterial) {
            $project = $numaMaterial->project;
            $this->line(sprintf(
                '- %s (%s): planned=%s reserved=%s',
                $project?->name ?? $numaMaterial->project_id,
                $project?->status ?? '?',
                $numaMaterial->planned_qty,
                $numaMaterial->reserved_qty
            ));

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($numaMaterial, $product, $whOcn, $whNuma, $reservationService): void {
                $ocnMaterial = ProjectMaterial::query()->firstOrNew([
                    'project_id' => $numaMaterial->project_id,
                    'master_product_id' => $product->id,
                    'warehouse_id' => $whOcn->id,
                ]);

                if (! $ocnMaterial->exists) {
                    $ocnMaterial->planned_qty = 0;
                    $ocnMaterial->reserved_qty = 0;
                    $ocnMaterial->issued_qty = 0;
                    $ocnMaterial->unit_cost = $numaMaterial->unit_cost;
                    $ocnMaterial->unit_price = $numaMaterial->unit_price;
                    $ocnMaterial->notes = $numaMaterial->notes;
                    $ocnMaterial->status = 'planned';
                }

                $ocnMaterial->planned_qty = (float) $ocnMaterial->planned_qty + (float) $numaMaterial->planned_qty;
                $ocnMaterial->unit_cost = $numaMaterial->unit_cost;
                $ocnMaterial->unit_price = $numaMaterial->unit_price;
                $ocnMaterial->notes = $numaMaterial->notes ?: $ocnMaterial->notes;
                $ocnMaterial->status = 'planned';
                $ocnMaterial->save();

                $numaMaterial->delete();

                $reservationService->syncWarehouseReservation($product->id, $whNuma->id);
                $reservationService->syncWarehouseReservation($product->id, $whOcn->id);
            });

            $this->info("CAMHLK007 pada project {$project?->name} dipindah WH-NUMA → WH-OCN.");
        }
    }
}
