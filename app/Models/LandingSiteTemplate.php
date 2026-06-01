<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingSiteTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'family_layout_key',
        'scope',
        'is_system',
        'is_active',
        'created_by',
        'description',
        'schema',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'bool',
            'is_active' => 'bool',
            'created_by' => 'int',
            'schema' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
