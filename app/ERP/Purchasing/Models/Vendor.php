<?php

namespace App\ERP\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'address',
        'tax_id',
        'payment_terms',
        'lead_time_days',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'lead_time_days' => 'int',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
