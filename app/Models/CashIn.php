<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashIn extends Model
{
    use HasUuids;

    protected $table = 'cash_in';

    protected $fillable = [
        'project_id',
        'project_payment_id',
        'category',
        'amount',
        'date',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function projectPayment()
    {
        return $this->belongsTo(ProjectPayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
