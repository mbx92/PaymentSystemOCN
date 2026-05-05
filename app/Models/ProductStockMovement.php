<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockMovement extends Model
{
    protected $fillable = [
        'master_product_id',
        'movement_date',
        'movement_type',
        'qty',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'qty' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }
}
