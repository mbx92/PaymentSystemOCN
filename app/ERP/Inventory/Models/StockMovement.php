<?php

namespace App\ERP\Inventory\Models;

use App\ERP\Shared\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use Auditable;

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'movement_date',
        'movement_type',
        'qty',
        'unit_cost',
        'reference_type',
        'reference_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }
}
