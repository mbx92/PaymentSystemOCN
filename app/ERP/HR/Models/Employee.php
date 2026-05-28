<?php

namespace App\ERP\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'company_id',
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
            'deleted_at' => 'datetime',
        ];
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }
}
