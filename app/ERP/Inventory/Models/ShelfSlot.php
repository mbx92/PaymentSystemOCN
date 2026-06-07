<?php

namespace App\ERP\Inventory\Models;

use App\Models\MasterProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShelfSlot extends Model
{
    protected $fillable = [
        'shelf_id',
        'tier',
        'slot_position',
        'product_id',
        'qty',
        'min_qty',
    ];

    protected function casts(): array
    {
        return [
            'tier' => 'int',
            'slot_position' => 'int',
            'product_id' => 'int',
            'qty' => 'int',
            'min_qty' => 'int',
        ];
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'product_id');
    }
}
