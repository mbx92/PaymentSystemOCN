<?php

namespace App\Models;

use App\ERP\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LandingSite extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'layout_key',
        'warehouse_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_id' => 'int',
            'is_active' => 'bool',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function page(): HasOne
    {
        return $this->hasOne(LandingSitePage::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(CmsAccessLog::class);
    }

    public function pageVersions(): HasMany
    {
        return $this->hasMany(LandingSitePageVersion::class);
    }
}
