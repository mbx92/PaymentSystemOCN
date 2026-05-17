<?php

namespace App\Models;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingInventoryRecord extends Model
{
    protected $fillable = [
        'item_name',
        'qty',
        'amount',
        'acquisition_date',
        'asset_account_id',
        'cash_account_id',
        'note',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'qty' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
