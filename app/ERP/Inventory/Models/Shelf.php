<?php

namespace App\ERP\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shelf extends Model
{
    protected $fillable = [
        'code',
        'name',
        'row_position',
        'col_position',
    ];

    protected function casts(): array
    {
        return [
            'row_position' => 'int',
            'col_position' => 'int',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ShelfSlot::class);
    }
}
