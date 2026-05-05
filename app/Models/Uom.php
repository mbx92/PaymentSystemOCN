<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uom extends Model
{
    protected $fillable = [
        'code',
        'name',
        'status',
    ];

    public function fromConversions(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'from_uom_id');
    }

    public function toConversions(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'to_uom_id');
    }
}
