<?php

namespace App\ERP\CRM\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmCustomer extends Model
{
    protected $table = 'crm_customers';

    protected $fillable = [
        'code',
        'company_id',
        'name',
        'company',
        'email',
        'phone',
        'address',
        'business_type',
        'tax_id',
        'source',
        'pic_user_id',
        'converted_from_lead_id',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'converted_from_lead_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'crm_customer_id');
    }
}
