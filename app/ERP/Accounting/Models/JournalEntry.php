<?php

namespace App\ERP\Accounting\Models;

use App\ERP\Core\Models\Company;
use App\ERP\Shared\Concerns\Auditable;
use App\ERP\Shared\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'company_id',
        'entry_no',
        'entry_date',
        'description',
        'status',
        'source_module',
        'source_reference',
        'posted_at',
        'posted_by',
        'reversed_entry_id',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    public function void(string $reason): void
    {
        $this->update([
            'status' => DocumentStatus::Void,
            'voided_at' => now(),
            'voided_by' => auth()->id(),
            'void_reason' => $reason,
        ]);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
