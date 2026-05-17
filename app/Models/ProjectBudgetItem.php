<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBudgetItem extends Model
{
    protected $fillable = [
        'project_budget_id',
        'master_product_id',
        'item_type',
        'name',
        'uom',
        'qty',
        'unit_cost',
        'unit_price',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'sort_order' => 'int',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(ProjectBudget::class, 'project_budget_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }

    public function getSubtotalCostAttribute(): float
    {
        return (float) $this->qty * (float) $this->unit_cost;
    }

    public function getSubtotalPriceAttribute(): float
    {
        return (float) $this->qty * (float) $this->unit_price;
    }

    public function getMarginAmountAttribute(): float
    {
        return $this->subtotal_price - $this->subtotal_cost;
    }
}
