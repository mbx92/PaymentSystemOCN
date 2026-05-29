<?php

namespace App\ERP\Inventory\Models;

use App\ERP\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

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
