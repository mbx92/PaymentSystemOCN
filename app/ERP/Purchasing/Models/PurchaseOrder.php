<?php

namespace App\ERP\Purchasing\Models;

use App\ERP\Shared\Concerns\Auditable;
use App\ERP\Shared\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use Auditable;

    protected $fillable = [
        'number',
        'vendor_id',
        'order_date',
        'eta_date',
        'total_amount',
        'po_category',
        'status',
        'notes',
        'approved_at',
        'approved_by',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'eta_date' => 'date',
            'total_amount' => 'decimal:2',
            'po_category' => 'string',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::Submitted], true);
    }

    public function isExpense(): bool
    {
        return $this->po_category === 'expense';
    }

    public function isInventory(): bool
    {
        return $this->po_category !== 'expense';
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
