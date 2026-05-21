<?php

namespace App\Services\ErpChatbot;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use Illuminate\Support\Collection;

class SalesQueryService
{
    public function summarizePeriod(string $startDate, string $endDate): array
    {
        $query = PosSale::query()
            ->whereBetween('sold_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);

        $count = (clone $query)->count();
        $total = (float) (clone $query)->sum('grand_total');

        return [
            'count' => $count,
            'total' => $total,
            'average' => $count > 0 ? $total / $count : 0.0,
        ];
    }

    public function topSellingProducts(string $startDate, string $endDate, int $limit = 10): Collection
    {
        return PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereBetween('pos_sales.sold_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->selectRaw('pos_sale_items.product_name, SUM(pos_sale_items.qty) as total_qty, SUM(pos_sale_items.line_total) as total_revenue')
            ->groupBy('pos_sale_items.product_name')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();
    }
}
