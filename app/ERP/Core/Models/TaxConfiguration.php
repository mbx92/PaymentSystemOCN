<?php

namespace App\ERP\Core\Models;

use Illuminate\Database\Eloquent\Model;

class TaxConfiguration extends Model
{
    protected $fillable = [
        'name',
        'rate',
        'is_withholding',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_withholding' => 'bool',
            'is_active' => 'bool',
        ];
    }
}
