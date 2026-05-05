<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'client_name',
        'client_contact',
        'total_value',
        'status',
        'started_at',
        'finished_at',
        'description',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'started_at'  => 'date',
        'finished_at' => 'date',
    ];

    public function payments()
    {
        return $this->hasMany(ProjectPayment::class)->orderBy('term_number');
    }

    public function cashIns()
    {
        return $this->hasMany(CashIn::class);
    }

    public function cashOuts()
    {
        return $this->hasMany(CashOut::class);
    }

    public function teamDistributions()
    {
        return $this->hasMany(TeamDistribution::class);
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }

    public function getTotalCashInAttribute(): float
    {
        return (float) $this->cashIns()->sum('amount');
    }

    public function getTotalCashOutAttribute(): float
    {
        return (float) $this->cashOuts()->sum('amount');
    }

    public function getProfitAttribute(): float
    {
        return $this->total_cash_in - $this->total_cash_out;
    }

    public function getTotalReferralCommissionAttribute(): float
    {
        return (float) $this->referrals()->sum('commission_amount');
    }

    public function getTotalOperationalAttribute(): float
    {
        return (float) $this->cashOuts()->where('category', 'operasional')->sum('amount');
    }

    public function getNetTeamValueAttribute(): float
    {
        return $this->total_value - $this->total_referral_commission - $this->total_operational;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'berjalan');
    }
}
