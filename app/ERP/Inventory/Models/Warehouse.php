<?php

namespace App\ERP\Inventory\Models;

use App\ERP\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = [
        'code',
        'company_id',
        'name',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'int',
            'is_active' => 'bool',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
