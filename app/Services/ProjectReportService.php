<?php

namespace App\Services;

use App\ERP\Purchasing\Models\PurchaseOrderLine;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class ProjectReportService
{
    private const PURCHASE_CATEGORIES = [
        'pembelian_material_project',
        'pembelian_bahan',
    ];

    public function build(Request $request): array
    {
        $status = $request->string('status')->toString();
        $projectType = $request->string('project_type')->toString();
        $term = $request->string('q')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        $projects = Project::query()
            ->with(['projectTypeDefinition', 'cashIns', 'cashOuts', 'materials', 'convertedBudget.items'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($projectType !== '', fn ($q) => $q->where('project_type', $projectType))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($term !== '', function ($q) use ($term): void {
                $q->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', '%'.$term.'%')
                        ->orWhere('client_name', 'like', '%'.$term.'%')
                        ->orWhere('invoice_number', 'like', '%'.$term.'%')
                        ->orWhere('description', 'like', '%'.$term.'%');
                });
            })
            ->latest('created_at')
            ->get();

        $latestPurchaseCosts = $this->latestPurchaseCostsForProjects($projects);

        $rows = $projects->map(function (Project $project) use ($latestPurchaseCosts): array {
            $contractValue = (float) $project->resolveListTotalValue();
            $cashIn = (float) $project->cashIns->sum('amount');
            $recordedPurchaseCost = (float) $project->cashOuts
                ->whereIn('category', self::PURCHASE_CATEGORIES)
                ->sum('amount');
            $directMaterialEstimatedCost = (float) $project->materials->sum(
                fn ($material) => (float) $material->planned_qty * $this->resolvedItemUnitCost(
                    explicitUnitCost: (float) $material->unit_cost,
                    productId: $material->master_product_id,
                    latestPurchaseCosts: $latestPurchaseCosts,
                )
            );
            $budgetItems = $project->convertedBudget?->items ?? collect();
            $budgetEstimatedCost = (float) $budgetItems->sum(
                fn ($item) => (float) $item->qty * $this->resolvedItemUnitCost(
                    explicitUnitCost: (float) $item->unit_cost,
                    productId: $item->master_product_id,
                    latestPurchaseCosts: $latestPurchaseCosts,
                )
            );
            $materialEstimatedCost = $budgetItems->isNotEmpty() ? $budgetEstimatedCost : $directMaterialEstimatedCost;
            $purchaseCost = $recordedPurchaseCost > 0 ? $recordedPurchaseCost : $materialEstimatedCost;
            $operationalCashOut = (float) $project->cashOuts
                ->whereNotIn('category', self::PURCHASE_CATEGORIES)
                ->sum('amount');
            $cashOut = $operationalCashOut + $purchaseCost;
            $profit = $cashIn - $cashOut;
            $collectionRate = $contractValue > 0 ? round(($cashIn / $contractValue) * 100, 1) : 0;

            return [
                'id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'project_type' => (string) $project->project_type,
                'project_type_label' => $project->projectTypeLabel(),
                'status' => $project->status,
                'invoice_number' => $project->invoice_number,
                'created_at' => $project->created_at?->format('Y-m-d'),
                'started_at' => $project->started_at?->format('Y-m-d'),
                'finished_at' => $project->finished_at?->format('Y-m-d'),
                'contract_value' => $contractValue,
                'cash_in' => $cashIn,
                'operational_cash_out' => $operationalCashOut,
                'purchase_cost' => $purchaseCost,
                'cash_out' => $cashOut,
                'profit' => $profit,
                'collection_rate' => $collectionRate,
            ];
        });

        return [
            'filters' => [
                'status' => $status,
                'project_type' => $projectType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'q' => $term,
            ],
            'summary' => [
                'project_count' => $rows->count(),
                'contract_value' => (float) $rows->sum('contract_value'),
                'cash_in' => (float) $rows->sum('cash_in'),
                'operational_cash_out' => (float) $rows->sum('operational_cash_out'),
                'purchase_cost' => (float) $rows->sum('purchase_cost'),
                'cash_out' => (float) $rows->sum('cash_out'),
                'profit' => (float) $rows->sum('profit'),
            ],
            'pivot' => [
                'status' => $this->groupByLabel($rows, 'status'),
                'project_type' => $this->groupByLabel($rows, 'project_type_label'),
            ],
            'projects' => $this->paginateCollection($rows->values(), $request),
        ];
    }

    private function latestPurchaseCostsForProjects(Collection $projects): Collection
    {
        $productIds = $projects
            ->flatMap(function (Project $project): array {
                $materialIds = $project->materials->pluck('master_product_id')->filter()->all();
                $budgetIds = ($project->convertedBudget?->items ?? collect())->pluck('master_product_id')->filter()->all();

                return [...$materialIds, ...$budgetIds];
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        return PurchaseOrderLine::query()
            ->whereIn('master_product_id', $productIds->all())
            ->orderByDesc('id')
            ->get(['master_product_id', 'unit_price'])
            ->groupBy('master_product_id')
            ->map(fn (Collection $rows) => (float) ($rows->first()->unit_price ?? 0));
    }

    private function resolvedItemUnitCost(float $explicitUnitCost, mixed $productId, Collection $latestPurchaseCosts): float
    {
        if ($explicitUnitCost > 0) {
            return $explicitUnitCost;
        }

        $resolvedProductId = (int) ($productId ?? 0);

        return (float) ($latestPurchaseCosts[$resolvedProductId] ?? 0);
    }

    private function groupByLabel(Collection $rows, string $field): array
    {
        return $rows->groupBy($field)
            ->map(function (Collection $items, string $label): array {
                return [
                    'label' => $label !== '' ? $label : 'Tanpa Label',
                    'count' => $items->count(),
                    'contract_value' => (float) $items->sum('contract_value'),
                    'cash_in' => (float) $items->sum('cash_in'),
                    'operational_cash_out' => (float) $items->sum('operational_cash_out'),
                    'purchase_cost' => (float) $items->sum('purchase_cost'),
                    'cash_out' => (float) $items->sum('cash_out'),
                    'profit' => (float) $items->sum('profit'),
                ];
            })
            ->sortByDesc('profit')
            ->values()
            ->all();
    }

    private function paginateCollection(Collection $items, Request $request, string $pageName = 'page'): LengthAwarePaginator
    {
        $perPage = (int) $request->query('per_page', 25);
        $allowed = [25, 50, 75, 100, 125, 150, 175, 200, 225, 250];
        $perPage = in_array($perPage, $allowed, true) ? $perPage : 25;
        $currentPage = Paginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage)->values()->all(),
            $items->count(),
            $perPage,
            $currentPage,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
                'query' => $request->query(),
            ],
        );
    }
}
