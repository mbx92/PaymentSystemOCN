<?php

namespace App\ERP\Accounting\Models;

use App\ERP\Shared\Concerns\Auditable;
use App\ERP\Shared\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;

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
}
