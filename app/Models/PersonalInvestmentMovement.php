<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalInvestmentMovement extends Model
{
    protected $fillable = [
        'investment_id',
        'occurred_on',
        'flow',
        'amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'investment_id' => 'int',
            'occurred_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(PersonalInvestment::class, 'investment_id');
    }
}
