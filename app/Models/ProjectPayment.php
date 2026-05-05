<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProjectPayment extends Model
{
    use HasUuids;
    protected $fillable = [
        'project_id',
        'term_number',
        'percentage',
        'amount',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount'     => 'decimal:2',
        'paid_at'    => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function cashInFromTerm()
    {
        return $this->hasOne(CashIn::class, 'project_payment_id');
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->paid_at !== null;
    }
}
