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
        'ticker',
        'asset_type',
        'institution',
        'notes',
        'opened_at',
        'is_active',
        'units_held',
        'current_price',
        'previous_close',
        'price_change',
        'price_change_percent',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'opened_at' => 'date',
            'is_active' => 'bool',
            'units_held' => 'decimal:4',
            'current_price' => 'decimal:2',
            'previous_close' => 'decimal:2',
            'price_change' => 'decimal:2',
            'price_change_percent' => 'decimal:4',
            'last_synced_at' => 'datetime',
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
