<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalContract extends Model
{
    protected $fillable = [
        'title',
        'contract_number',
        'contract_date',
        'contract_type',
        'pihak_pertama',
        'pihak_kedua',
        'pasals',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'pihak_pertama' => 'array',
        'pihak_kedua' => 'array',
        'pasals' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
