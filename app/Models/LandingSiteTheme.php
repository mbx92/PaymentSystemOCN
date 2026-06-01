<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingSiteTheme extends Model
{
    protected $fillable = [
        'key',
        'name',
        'scope',
        'is_system',
        'is_active',
        'created_by',
        'description',
        'tokens',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'bool',
            'is_active' => 'bool',
            'created_by' => 'int',
            'tokens' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
