<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashOut extends Model
{
    use HasUuids;

    protected $table = 'cash_out';

    protected $fillable = [
        'project_id',
        'category',
        'amount',
        'date',
        'note',
        'recipient_name',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
