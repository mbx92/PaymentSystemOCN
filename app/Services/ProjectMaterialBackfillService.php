<?php

namespace App\Services;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Accounting\Services\CoaSettingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\ProjectMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectMaterialBackfillService
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
    ) {}

    public function summarize(): array
    {
        $materials = $this->materialsWithIssues();
        $totalQty = $materials->sum('issued_qty');
        $totalCost = $materials->sum(fn (ProjectMaterial $m) => (float) ($m->unit_cost ?? 0) * (float) $m->issued_qty);

        $cogsAccount = app(CoaSettingService::class)
            ->resolveAccountByKey('pos_sale_cogs_account', '5009');
        $inventoryAccount = Account::query()->where('code', '1201')->first();

        $projects = Project::query()
            ->whereIn('id', $materials->pluck('project_id')->unique())
            ->get(['id', 'name'])
            ->keyBy('id');

        return [
            'materials_count' => $materials->count(),
            'projects_count' => $materials->pluck('project_id')->unique()->count(),
            'total_issued_qty' => $totalQty,
            'total_estimated_cost' => $totalCost,
            'materials' => $materials->map(fn (ProjectMaterial $m) => [
                'project_name' => $projects->get($m->project_id)?->name ?? "Project #{$m->project_id}",
                'product_name' => $m->product?->name ?? "Product #{$m->master_product_id}",
                'issued_qty' => (float) $m->issued_qty,
                'unit_cost' => (float) ($m->unit_cost ?? 0),
                'estimated_cost' => (float) ($m->unit_cost ?? 0) * (float) $m->issued_qty,
            ])->take(50),
            'cogs_account_label' => $cogsAccount?->code.' - '.$cogsAccount?->name,
            'inventory_account_label' => $inventoryAccount?->code.' - '.$inventoryAccount?->name,
            'can_run' => $cogsAccount !== null && $inventoryAccount !== null,
            'message' => $cogsAccount === null
                ? 'Akun HPP/COGS belum dikonfigurasi. Buka CoA Settings untuk mengatur.'
                : ($inventoryAccount === null
                    ? 'Akun Persediaan (1201) tidak ditemukan.'
                    : null),
        ];
    }

    public function materialsWithIssues(): Collection
    {
        return ProjectMaterial::query()
            ->with('product')
            ->where('issued_qty', '>', 0)
            ->get();
    }

    public function backfill(): array
    {
        $materials = $this->materialsWithIssues();
        $cogsAccount = app(CoaSettingService::class)
            ->resolveAccountByKey('pos_sale_cogs_account', '5009');
        $inventoryAccount = Account::query()->where('code', '1201')->firstOrFail();

        if (! $cogsAccount) {
            return ['succeeded' => 0, 'skipped' => $materials->count(), 'total_cost' => 0.0, 'errors' => ['Akun HPP/COGS belum dikonfigurasi.']];
        }

        $succeeded = 0;
        $totalCost = 0.0;
        $errors = [];

        $grouped = $materials->groupBy('project_id');

        foreach ($grouped as $projectId => $projectMaterials) {
            $project = Project::query()->find($projectId);
            $companyId = ErpCompanyResolver::resolveForGlPosting(request());

            $cogsAmount = 0.0;
            foreach ($projectMaterials as $material) {
                $cogsAmount += (float) ($material->unit_cost ?? 0) * (float) $material->issued_qty;
            }

            if ($cogsAmount <= 0) {
                $errors[] = "Project #{$projectId}: total COGS = 0 (set unit_cost produk dulu)";

                continue;
            }

            $ref = $project?->invoice_number ?? "PRJ-{$projectId}";
            $entryDate = $project?->started_at?->toDateString()
                ?? $projectMaterials->sortBy('created_at')->first()?->created_at?->toDateString()
                ?? now()->toDateString();

            try {
                $this->glPostingService->post(
                    $companyId,
                    sourceModule: 'project_material_cogs',
                    sourceReference: $ref,
                    description: 'Biaya material proyek '.($project?->name ?? "#{$projectId}").' (backfill)',
                    entryDate: $entryDate,
                    lines: [
                        ['account_id' => $cogsAccount->id, 'debit' => $cogsAmount, 'credit' => 0],
                        ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $cogsAmount],
                    ]
                );
                $succeeded++;
                $totalCost += $cogsAmount;
            } catch (\Throwable $e) {
                $errors[] = "Project #{$projectId}: {$e->getMessage()}";
            }
        }

        return [
            'succeeded' => $succeeded,
            'skipped' => $grouped->count() - $succeeded,
            'total_cost' => $totalCost,
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    public function estimateUnitCosts(): array
    {
        $productIds = ProjectMaterial::query()
            ->where('issued_qty', '>', 0)
            ->where(function ($q): void {
                $q->whereNull('unit_cost')->orWhere('unit_cost', 0);
            })
            ->pluck('master_product_id')
            ->unique();

        $checked = $productIds->count();
        $updated = 0;

        foreach ($productIds as $productId) {
            $price = (float) DB::table('purchase_order_lines')
                ->where('master_product_id', $productId)
                ->where('unit_price', '>', 0)
                ->orderByDesc('id')
                ->value('unit_price');

            if ($price > 0) {
                MasterProduct::query()->where('id', $productId)->update(['unit_cost' => $price]);
                $updated++;
            }
        }

        return [
            'products_checked' => $checked,
            'products_updated' => $updated,
        ];
    }
}
