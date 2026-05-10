<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalBudget extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'year',
        'month',
        'amount_limit',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'category_id' => 'int',
            'year' => 'int',
            'month' => 'int',
            'amount_limit' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PersonalCategory::class, 'category_id');
    }
}
