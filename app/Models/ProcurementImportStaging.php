<?php

namespace App\Models;

use App\ERP\Core\Models\Company;
use App\ERP\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementImportStaging extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'company_id',
        'warehouse_id',
        'source_import_key',
        'legacy_project_number',
        'legacy_project_name',
        'procurement_date',
        'status',
        'notes',
        'created_by',
        'converted_at',
        'converted_by',
        'conversion_summary',
    ];

    protected function casts(): array
    {
        return [
            'procurement_date' => 'date',
            'company_id' => 'int',
            'warehouse_id' => 'int',
            'created_by' => 'int',
            'converted_at' => 'datetime',
            'converted_by' => 'int',
            'conversion_summary' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementImportStagingLine::class);
    }
}
