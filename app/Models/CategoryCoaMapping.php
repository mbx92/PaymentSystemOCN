<?php

namespace App\Models;

use App\ERP\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryCoaMapping extends Model
{
    protected $fillable = [
        'domain',
        'category',
        'account_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
