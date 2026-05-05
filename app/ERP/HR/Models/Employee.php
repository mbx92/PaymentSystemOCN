<?php

namespace App\ERP\HR\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'employee_no',
        'name',
        'email',
        'phone',
        'position',
        'base_salary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'is_active' => 'bool',
        ];
    }
}
