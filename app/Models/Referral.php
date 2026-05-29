<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'referrer_name',
        'commission_amount',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'commission_amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
