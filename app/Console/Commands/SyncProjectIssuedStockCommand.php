<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Services\ProjectMaterialStockIssueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProjectIssuedStockCommand extends Command
{
    protected $signature = 'stock:sync-project-issues
        {project_id? : UUID/ID project yang ingin disinkronkan}
        {--all : Sinkronkan semua project yang memiliki material issued}
        {--apply : Terapkan perubahan stock dan movement}';

    protected $description = 'Preview atau sinkronkan stock issue project agar issued_qty selaras dengan movement project_issue_*';

    public function handle(ProjectMaterialStockIssueService $issueService): int
    {
        $projectId = $this->argument('project_id');
        $syncAll = (bool) $this->option('all');
        $apply = (bool) $this->option('apply');

        if (! $projectId && ! $syncAll) {
            $this->error('Isi {project_id} atau gunakan --all.');

            return self::FAILURE;
        }

        $projects = Project::query()
            ->with(['materials.product'])
            ->when($projectId, fn ($query) => $query->where('id', (string) $projectId))
            ->when($syncAll && ! $projectId, fn ($query) => $query->whereHas('materials', fn ($materialQuery) => $materialQuery->where('issued_qty', '>', 0)))
            ->orderBy('name')
            ->get();

        if ($projects->isEmpty()) {
            $this->error('Project tidak ditemukan atau tidak memiliki material issued.');

            return self::FAILURE;
        }

        $rows = [];
        $previewRows = [];
        $warnings = [];

        foreach ($projects as $project) {
            $projectRows = $project->materials
                ->filter(fn (ProjectMaterial $material) => $material->product?->isStockTracked() && (int) $material->warehouse_id > 0)
                ->map(fn (ProjectMaterial $material) => $issueService->preview($material))
                ->values();

            foreach ($projectRows as $row) {
                $previewRows[] = $row + [
                    'project_name' => $project->name,
                    'project_status' => $project->status,
                ];

                if ($row['warehouse_qty_after'] < -0.00001) {
                    $warnings[] = sprintf(
                        '[%s] %s akan minus %.2f jika disinkronkan.',
                        $project->name,
                        $row['sku'] ?? ('Material #'.$row['material_id']),
                        abs($row['warehouse_qty_after']),
                    );
                }
            }
        }

        if ($previewRows === []) {
            $this->info('Tidak ada material stock-tracked yang perlu dicek.');

            return self::SUCCESS;
        }

        $this->table(
            ['Project', 'Status', 'SKU', 'WH', 'Target Issued', 'Movement Net', 'Delta', 'WH Before', 'WH After', 'Action'],
            array_map(fn (array $row): array => [
                $row['project_name'],
                $row['project_status'],
                $row['sku'],
                $row['warehouse_id'],
                $row['target_issued_qty'],
                $row['movement_net_before'],
                $row['delta_qty'],
                $row['warehouse_qty_before'],
                $row['warehouse_qty_after'],
                $row['action'],
            ], $previewRows),
        );

        if ($warnings !== []) {
            $this->warn('Warnings:');
            foreach ($warnings as $warning) {
                $this->line('- '.$warning);
            }
        }

        if (! $apply) {
            $this->newLine();
            $this->line('Preview only. Tambahkan `--apply` untuk menerapkan sinkronisasi.');

            return self::SUCCESS;
        }

        if ($warnings !== []) {
            $this->error('Sinkronisasi dibatalkan karena ada baris yang akan membuat stock minus.');

            return self::FAILURE;
        }

        foreach ($projects as $project) {
            DB::transaction(function () use ($project, $issueService, &$rows): void {
                $materials = ProjectMaterial::query()
                    ->with(['product', 'project'])
                    ->where('project_id', $project->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($materials as $material) {
                    if (! $material->product?->isStockTracked() || (int) $material->warehouse_id <= 0) {
                        continue;
                    }

                    $rows[] = $issueService->sync($material, (float) $material->issued_qty);
                }
            });
        }

        $changed = collect($rows)->filter(fn (array $row) => $row['action'] !== 'noop')->count();

        $this->info("Sinkronisasi selesai. {$changed} baris movement/stock diperbarui.");

        return self::SUCCESS;
    }
}
