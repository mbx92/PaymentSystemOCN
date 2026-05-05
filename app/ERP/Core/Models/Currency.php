<?php

namespace App\ERP\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_base',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'int',
            'is_base' => 'bool',
        ];
    }
}
