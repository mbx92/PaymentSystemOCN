<?php

namespace App\ERP\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPipeline extends Model
{
    protected $table = 'crm_pipelines';

    public const STAGES = [
        'prospecting',
        'qualification',
        'proposal',
        'negotiation',
        'closed_won',
        'closed_lost',
    ];

    protected $fillable = [
        'code',
        'title',
        'crm_customer_id',
        'crm_lead_id',
        'stage',
        'deal_value',
        'win_probability',
        'expected_close_date',
        'pic_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'deal_value' => 'decimal:2',
            'win_probability' => 'integer',
            'expected_close_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CrmCustomer::class, 'crm_customer_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'crm_pipeline_id');
    }
}
