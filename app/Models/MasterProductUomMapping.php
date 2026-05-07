<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterProductUomMapping extends Model
{
    protected $fillable = [
        'master_product_id',
        'uom_code',
        'multiplier',
        'price_operation',
        'selling_price',
        'use_auto_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
            'selling_price' => 'decimal:2',
            'use_auto_price' => 'bool',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }
}

