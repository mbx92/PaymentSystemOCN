<?php

namespace App\Models;

use App\ERP\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMaterial extends Model
{
    protected $fillable = [
        'project_id',
        'master_product_id',
        'warehouse_id',
        'planned_qty',
        'reserved_qty',
        'issued_qty',
        'unit_cost',
        'unit_price',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_qty' => 'decimal:2',
            'reserved_qty' => 'decimal:2',
            'issued_qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
