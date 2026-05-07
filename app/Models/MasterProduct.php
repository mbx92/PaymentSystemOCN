<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
}
