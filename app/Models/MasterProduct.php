<?php

namespace App\Models;

use App\ERP\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterProduct extends Model
{
    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'category',
        'uom',
        'sales_channel',
        'product_type',
        'status',
        'description',
        'selling_price',
        'stock',
        'min_stock',
        'total_sold',
        'lead_time_days',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'stock' => 'int',
            'min_stock' => 'int',
            'total_sold' => 'int',
            'lead_time_days' => 'int',
        ];
    }

    public function uomMappings(): HasMany
    {
        return $this->hasMany(MasterProductUomMapping::class)->orderBy('uom_code');
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(MasterProductWarehouseStock::class);
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'master_product_warehouse_stocks')
            ->withPivot('qty', 'reserved_qty');
    }

    /**
     * Generate a unique SKU from category name: {PREFIX}-{00001}.
     */
    public static function generateSku(string $category): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category) ?: 'PRD', 0, 3));

        $last = static::query()
            ->where('sku', 'like', $prefix.'-%')
            ->orderByRaw("CAST(SUBSTRING(sku FROM '[0-9]+$') AS INTEGER) DESC")
            ->value('sku');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique EAN-13 internal barcode (prefix 20 = internal use).
     */
    public static function generateBarcode(): string
    {
        $yymm = now()->format('ym');

        $last = static::query()
            ->where('barcode', 'like', '20'.$yymm.'%')
            ->orderByDesc('barcode')
            ->value('barcode');

        $next = 1;
        if ($last) {
            $seqPart = substr((string) $last, 6, 6);
            $next = ((int) $seqPart) + 1;
        }

        $body = '20'.$yymm.str_pad((string) $next, 6, '0', STR_PAD_LEFT);

        return $body.static::ean13CheckDigit($body);
    }

    private static function ean13CheckDigit(string $digits12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits12[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }
}
