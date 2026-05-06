<?php

namespace App\ERP\Purchasing\Models;

use App\Models\MasterProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'master_product_id',
        'qty',
        'received_qty',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'received_qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }
}

