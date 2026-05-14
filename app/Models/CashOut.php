<?php

namespace App\Models;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Shared\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashOut extends Model
{
    use Auditable, HasUuids;

    protected $table = 'cash_out';

    protected $fillable = [
        'project_id',
        'cash_account_id',
        'category',
        'amount',
        'document_status',
        'approved_at',
        'approved_by',
        'posted_at',
        'posted_by',
        'journal_entry_id',
        'date',
        'note',
        'recipient_name',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashAccount()
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
