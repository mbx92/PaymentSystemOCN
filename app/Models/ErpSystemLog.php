<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpSystemLog extends Model
{
    protected $fillable = [
        'channel',
        'level',
        'event',
        'message',
        'user_id',
        'ip_address',
        'method',
        'path',
        'status_code',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
