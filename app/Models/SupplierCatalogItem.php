<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCatalogItem extends Model
{
    protected $fillable = [
        'ref',
        'sheet_key',
        'sheet_label',
        'supplier_name',
        'code',
        'name',
        'category',
        'supplier_price',
        'last_price',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'supplier_price' => 'decimal:2',
            'last_price' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }
}
