<?php

namespace App\ERP\Accounting\Models;

use App\ERP\Shared\Concerns\Auditable;
use App\ERP\Shared\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use Auditable;

    protected $fillable = [
        'entry_no',
        'entry_date',
        'description',
        'status',
        'source_module',
        'source_reference',
        'posted_at',
        'posted_by',
        'reversed_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
