<?php

namespace App\Models;

use App\ERP\Shared\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use Auditable;

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
    ];
}

