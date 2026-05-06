<?php

namespace App\Models;

use App\ERP\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterProductWarehouseStock extends Model
{
    protected $fillable = [
        'master_product_id',
        'warehouse_id',
        'qty',
        'reserved_qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'reserved_qty' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

