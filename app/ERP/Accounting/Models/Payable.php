<?php

namespace App\ERP\Accounting\Models;

use App\ERP\Shared\Concerns\Auditable;
use App\ERP\Shared\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payable extends Model
{
    use Auditable;

    protected $fillable = [
        'vendor_id',
        'purchase_order_id',
        'goods_receipt_id',
        'bill_no',
        'bill_date',
        'due_date',
        'amount',
        'paid_amount',
        'status',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => DocumentStatus::class,
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\ERP\Purchasing\Models\Vendor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(\App\ERP\Purchasing\Models\PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(\App\ERP\Purchasing\Models\GoodsReceipt::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayablePayment::class);
    }
}
