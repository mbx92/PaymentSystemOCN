<?php

namespace App\ERP\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoaSetting extends Model
{
    protected $table = 'accounting_coa_settings';

    protected $fillable = [
        'key',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}

