<?php

namespace App\Models;

use App\ERP\Purchasing\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementImportStagingLine extends Model
{
    protected $fillable = [
        'procurement_import_staging_id',
        'master_product_id',
        'vendor_id',
        'legacy_item_id',
        'legacy_product_sku',
        'product_name',
        'unit',
        'qty',
        'unit_cost',
        'line_total',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'master_product_id' => 'int',
            'vendor_id' => 'int',
            'qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function staging(): BelongsTo
    {
        return $this->belongsTo(ProcurementImportStaging::class, 'procurement_import_staging_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
