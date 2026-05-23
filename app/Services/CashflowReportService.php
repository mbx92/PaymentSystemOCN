<?php

namespace App\Services;

use App\Models\CashIn;
use App\Models\CashOut;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class CashflowReportService
{
    public function build(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $source = $this->normalizeOption($request->string('source')->toString(), ['all', 'project', 'manual'], 'all');
        $groupBy = $this->normalizeOption($request->string('group_by')->toString(), ['day', 'week', 'month'], 'day');
        $projectId = $request->string('project_id')->toString();
        $projectId = $projectId !== '' && $projectId !== 'all' ? $projectId : null;

        $cashIns = CashIn::query()
            ->with(['project:id,name', 'paymentMethod:id,name', 'creator:id,name'])
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($source === 'project', fn ($q) => $q->whereNotNull('project_id'))
            ->when($source === 'manual', fn ($q) => $q->whereNull('project_id'))
            ->orderBy('date')
            ->get();

        $cashOuts = CashOut::query()
            ->with(['project:id,name', 'creator:id,name'])
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($source === 'project', fn ($q) => $q->whereNotNull('project_id'))
            ->when($source === 'manual', fn ($q) => $q->whereNull('project_id'))
            ->orderBy('date')
            ->get();

        $transactions = $cashIns->map(function (CashIn $cashIn): array {
            return [
                'id' => $cashIn->id,
                'date' => $cashIn->date?->toDateString(),
                'direction' => 'in',
                'source' => $cashIn->project_id ? 'Project' : 'Manual / Umum',
                'project_id' => $cashIn->project_id,
                'project_name' => $cashIn->project?->name ?? 'Manual / Umum',
                'category' => $cashIn->category,
                'counterparty' => $cashIn->project?->name ?? '-',
                'payment_method' => $cashIn->paymentMethod?->name ?? '-',
                'note' => $cashIn->note,
                'amount' => (float) $cashIn->amount,
                'created_by' => $cashIn->creator?->name ?? '-',
            ];
        })->concat(
            $cashOuts->map(function (CashOut $cashOut): array {
                return [
                    'id' => $cashOut->id,
                    'date' => $cashOut->date?->toDateString(),
                    'direction' => 'out',
                    'source' => $cashOut->project_id ? 'Project' : 'Manual / Umum',
                    'project_id' => $cashOut->project_id,
                    'project_name' => $cashOut->project?->name ?? 'Manual / Umum',
                    'category' => $cashOut->category,
                    'counterparty' => $cashOut->recipient_name ?: '-',
                    'payment_method' => '-',
                    'note' => $cashOut->note,
                    'amount' => (float) $cashOut->amount,
                    'created_by' => $cashOut->creator?->name ?? '-',
                ];
            })
        )->sortBy([
            ['date', 'asc'],
            ['direction', 'asc'],
        ])->values();

        $totalIn = (float) $cashIns->sum('amount');
        $totalOut = (float) $cashOuts->sum('amount');

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'source' => $source,
                'project_id' => $projectId ?? 'all',
                'group_by' => $groupBy,
            ],
            'summary' => [
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'net_cashflow' => $totalIn - $totalOut,
                'transaction_count' => $transactions->count(),
                'cash_in_count' => $cashIns->count(),
                'cash_out_count' => $cashOuts->count(),
            ],
            'pivot' => [
                'timeline' => $this->pivotTimeline($transactions, $groupBy),
                'categories' => $this->pivotByKey($transactions, fn (array $row) => $this->categoryLabel($row['category'])),
                'projects' => $this->pivotByKey($transactions, fn (array $row) => $row['project_name']),
                'sources' => $this->pivotByKey($transactions, fn (array $row) => $row['source']),
            ],
            'transactions' => $this->paginateCollection(
                $transactions->sortByDesc('date')->values(),
                $request,
            ),
        ];
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

    private function resolveDateRange(Request $request): array
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->string('date_from')->toString())->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->string('date_to')->toString())->endOfDay()
            : now()->endOfMonth();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$dateFrom, $dateTo];
    }

    private function normalizeOption(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function pivotByKey(Collection $transactions, callable $resolver): array
    {
        return $transactions
            ->groupBy($resolver)
            ->map(function (Collection $rows, string $label): array {
                $totalIn = (float) $rows->where('direction', 'in')->sum('amount');
                $totalOut = (float) $rows->where('direction', 'out')->sum('amount');

                return [
                    'label' => $label,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'net' => $totalIn - $totalOut,
                    'count' => $rows->count(),
                ];
            })
            ->sortByDesc('net')
            ->values()
            ->all();
    }

    private function pivotTimeline(Collection $transactions, string $groupBy): array
    {
        return $transactions
            ->groupBy(function (array $row) use ($groupBy): string {
                $date = Carbon::parse($row['date']);

                return match ($groupBy) {
                    'month' => $date->format('Y-m'),
                    'week' => $date->format('Y').'-W'.str_pad((string) $date->weekOfYear, 2, '0', STR_PAD_LEFT),
                    default => $date->toDateString(),
                };
            })
            ->map(function (Collection $rows, string $bucket): array {
                $totalIn = (float) $rows->where('direction', 'in')->sum('amount');
                $totalOut = (float) $rows->where('direction', 'out')->sum('amount');

                return [
                    'bucket' => $bucket,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'net' => $totalIn - $totalOut,
                    'count' => $rows->count(),
                ];
            })
            ->sortBy('bucket')
            ->values()
            ->all();
    }

    private function categoryLabel(?string $category): string
    {
        $key = (string) $category;

        return match ($key) {
            'pendapatan_jasa' => 'Pendapatan Jasa',
            'pendapatan_project' => 'Pendapatan Project',
            'biaya_tim' => 'Biaya Tim',
            'komisi_referral' => 'Komisi Referral',
            'operasional' => 'Operasional',
            'lainnya' => 'Lainnya',
            default => $key !== '' ? str($key)->replace('_', ' ')->title()->toString() : 'Tanpa Kategori',
        };
    }
}
