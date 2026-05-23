<?php

namespace App\Services;

use App\Models\PosSale;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class PosReportService
{
    public function build(Request $request): array
    {
        $status = $request->string('status')->toString();
        $channel = $request->string('channel')->toString();
        $paymentMethodId = $request->string('payment_method_id')->toString();
        $term = $request->string('q')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        $sales = PosSale::query()
            ->with(['paymentMethod:id,name', 'soldBy:id,name', 'items:id,pos_sale_id'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($channel !== '', fn ($q) => $q->where('sales_channel', $channel))
            ->when($paymentMethodId !== '' && $paymentMethodId !== 'all', fn ($q) => $q->where('payment_method_id', $paymentMethodId))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('sold_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('sold_at', '<=', $dateTo))
            ->when($term !== '', function ($q) use ($term): void {
                $q->where(function ($inner) use ($term): void {
                    $inner->where('number', 'like', '%'.$term.'%')
                        ->orWhere('marketplace_order_code', 'like', '%'.$term.'%')
                        ->orWhere('note', 'like', '%'.$term.'%')
                        ->orWhereHas('soldBy', fn ($u) => $u->where('name', 'like', '%'.$term.'%'));
                });
            })
            ->latest('sold_at')
            ->get();

        $rows = $sales->map(function (PosSale $sale): array {
            return [
                'id' => $sale->id,
                'number' => $sale->number,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i:s'),
                'status' => $sale->status,
                'sales_channel' => (string) ($sale->sales_channel ?: 'retail'),
                'sales_channel_label' => $this->channelLabel((string) ($sale->sales_channel ?: 'retail')),
                'payment_method' => $sale->paymentMethod?->name ?? '-',
                'cashier' => $sale->soldBy?->name ?? '-',
                'items_count' => $sale->items->count(),
                'gross_total' => (float) $sale->gross_total,
                'discount_total' => (float) $sale->discount_total,
                'additional_fee' => (float) $sale->additional_fee,
                'admin_fee' => (float) ($sale->sales_channel_admin_fee ?? 0),
                'grand_total' => (float) $sale->grand_total,
                'cash_paid' => (float) $sale->cash_paid,
                'change_amount' => (float) $sale->change_amount,
                'marketplace_order_code' => $sale->marketplace_order_code,
            ];
        });

        return [
            'filters' => [
                'status' => $status,
                'channel' => $channel,
                'payment_method_id' => $paymentMethodId !== '' ? $paymentMethodId : 'all',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'q' => $term,
            ],
            'summary' => [
                'transaction_count' => $rows->count(),
                'gross_total' => (float) $rows->sum('gross_total'),
                'discount_total' => (float) $rows->sum('discount_total'),
                'grand_total' => (float) $rows->sum('grand_total'),
                'refund_count' => (int) $rows->where('status', 'refunded')->count(),
            ],
            'pivot' => [
                'status' => $this->groupByLabel($rows, 'status'),
                'channel' => $this->groupByLabel($rows, 'sales_channel_label'),
                'payment_method' => $this->groupByLabel($rows, 'payment_method'),
            ],
            'transactions' => $this->paginateCollection($rows->values(), $request),
        ];
    }

    private function groupByLabel(Collection $rows, string $field): array
    {
        return $rows->groupBy($field)
            ->map(function (Collection $items, string $label): array {
                return [
                    'label' => $label !== '' ? $label : 'Tanpa Label',
                    'count' => $items->count(),
                    'gross_total' => (float) $items->sum('gross_total'),
                    'discount_total' => (float) $items->sum('discount_total'),
                    'grand_total' => (float) $items->sum('grand_total'),
                ];
            })
            ->sortByDesc('grand_total')
            ->values()
            ->all();
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            'retail' => 'Retail',
            'grosir' => 'Grosir',
            'marketplace' => 'Marketplace',
            'project' => 'Project',
            default => str($channel)->replace('_', ' ')->title()->toString(),
        };
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
