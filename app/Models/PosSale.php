<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    protected $fillable = [
        'number',
        'payment_method_id',
        'gross_total',
        'discount_total',
        'additional_fee',
        'grand_total',
        'cash_paid',
        'change_amount',
        'status',
        'sold_at',
        'sold_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'gross_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'additional_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'cash_paid' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function additionalCharges(): HasMany
    {
        return $this->hasMany(PosSaleAdditionalCharge::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }
}
