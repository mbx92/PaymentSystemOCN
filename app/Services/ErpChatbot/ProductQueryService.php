<?php

namespace App\Services\ErpChatbot;

use App\Models\MasterProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductQueryService
{
    public function findActiveProductById(int $id): ?MasterProduct
    {
        return MasterProduct::query()
            ->where('status', 'active')
            ->find($id);
    }

    public function searchActiveProducts(string $term, int $limit = 6): Collection
    {
        $termLower = Str::lower($term);

        return MasterProduct::query()
            ->where('status', 'active')
            ->where(function ($query) use ($termLower): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$termLower.'%'])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ['%'.$termLower.'%'])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ['%'.$termLower.'%']);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'barcode', 'uom', 'stock', 'min_stock', 'selling_price']);
    }

    public function lowStockProducts(int $limit = 10): Collection
    {
        return MasterProduct::query()
            ->where('status', 'active')
            ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
            ->where('low_stock_alert_enabled', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'stock', 'min_stock', 'uom', 'low_stock_alert_enabled']);
    }
}
