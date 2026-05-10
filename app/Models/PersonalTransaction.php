<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_id',
        'category_id',
        'type',
        'amount',
        'occurred_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'wallet_id' => 'int',
            'category_id' => 'int',
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(PersonalWallet::class, 'wallet_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PersonalCategory::class, 'category_id');
    }
}
