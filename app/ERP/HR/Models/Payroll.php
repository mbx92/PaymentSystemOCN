<?php

namespace App\ERP\HR\Models;

use App\ERP\Shared\Concerns\Auditable;
use App\ERP\Shared\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id',
        'period_month',
        'period_year',
        'gross_amount',
        'deduction_amount',
        'net_amount',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'status' => DocumentStatus::class,
            'paid_at' => 'datetime',
        ];
    }
}
