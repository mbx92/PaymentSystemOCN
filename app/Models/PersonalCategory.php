<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalCategory extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PersonalTransaction::class, 'category_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(PersonalBudget::class, 'category_id');
    }
}
