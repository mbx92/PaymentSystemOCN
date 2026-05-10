<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalWallet extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'currency',
        'sort_order',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'sort_order' => 'int',
            'is_default' => 'bool',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PersonalTransaction::class, 'wallet_id');
    }
}
