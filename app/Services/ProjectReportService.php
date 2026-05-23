<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class ProjectReportService
{
    public function build(Request $request): array
    {
        $status = $request->string('status')->toString();
        $projectType = $request->string('project_type')->toString();
        $term = $request->string('q')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        $projects = Project::query()
            ->with(['projectTypeDefinition', 'cashIns', 'cashOuts'])
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

        $rows = $projects->map(function (Project $project): array {
            $contractValue = (float) $project->resolveListTotalValue();
            $cashIn = (float) $project->cashIns->sum('amount');
            $cashOut = (float) $project->cashOuts->sum('amount');
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

    private function groupByLabel(Collection $rows, string $field): array
    {
        return $rows->groupBy($field)
            ->map(function (Collection $items, string $label): array {
                return [
                    'label' => $label !== '' ? $label : 'Tanpa Label',
                    'count' => $items->count(),
                    'contract_value' => (float) $items->sum('contract_value'),
                    'cash_in' => (float) $items->sum('cash_in'),
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
