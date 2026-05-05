<?php

namespace App\Models;

use App\ERP\Shared\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProjectPayment extends Model
{
    use Auditable, HasUuids;

    protected $fillable = [
        'project_id',
        'term_number',
        'percentage',
        'amount',
        'document_status',
        'approved_at',
        'approved_by',
        'posted_at',
        'posted_by',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'paid_at' => 'date',
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
