<?php

namespace App\Models;

use App\ERP\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingSite extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'layout_key',
        'warehouse_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_id' => 'int',
            'is_active' => 'bool',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

