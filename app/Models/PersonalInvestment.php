<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalInvestment extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'asset_type',
        'institution',
        'notes',
        'opened_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'opened_at' => 'date',
            'is_active' => 'bool',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PersonalInvestmentMovement::class, 'investment_id')->orderByDesc('occurred_on')->orderByDesc('id');
    }
}
