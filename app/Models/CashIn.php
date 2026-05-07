<?php

namespace App\Models;

use App\ERP\Shared\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashIn extends Model
{
    use Auditable, HasUuids;

    protected $table = 'cash_in';

    protected $fillable = [
        'project_id',
        'project_payment_id',
        'payment_method_id',
        'category',
        'amount',
        'document_status',
        'approved_at',
        'approved_by',
        'posted_at',
        'posted_by',
        'journal_entry_id',
        'date',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function projectPayment()
    {
        return $this->belongsTo(ProjectPayment::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
