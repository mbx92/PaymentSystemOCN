<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationRead extends Model
{
    protected $fillable = [
        'user_id',
        'notification_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
