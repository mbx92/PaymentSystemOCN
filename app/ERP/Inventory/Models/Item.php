<?php

namespace App\ERP\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'unit',
        'standard_cost',
        'selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_active' => 'bool',
        ];
    }
}
