<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TeamDistribution extends Model
{
    use HasUuids;
    protected $fillable = [
        'project_id',
        'user_id',
        'role_in_project',
        'percentage',
        'base_pay',
        'bonus',
        'total_pay',
        'cash_out_id',
        'paid_at',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'base_pay'   => 'decimal:2',
        'bonus'      => 'decimal:2',
        'total_pay'  => 'decimal:2',
        'paid_at'    => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashOut()
    {
        return $this->belongsTo(CashOut::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null && $this->cash_out_id !== null;
    }
}
