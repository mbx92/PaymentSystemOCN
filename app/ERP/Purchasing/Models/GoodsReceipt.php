<?php

namespace App\ERP\Purchasing\Models;

use App\ERP\Shared\Concerns\Auditable;
use App\ERP\Shared\Enums\DocumentStatus;
use App\ERP\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    use Auditable;

    protected $fillable = [
        'number',
        'purchase_order_id',
        'received_date',
        'warehouse_id',
        'warehouse_name',
        'status',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'posted_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}

