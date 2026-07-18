<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBudget extends Model
{
    protected $fillable = [
        'name',
        'crm_customer_id',
        'client_name',
        'client_contact',
        'project_type',
        'estimated_value',
        'cctv_items',
        'description',
        'status',
        'deal_at',
        'converted_project_id',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'cctv_items' => 'array',
            'deal_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(ProjectBudgetItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function projectTypeDefinition()
    {
        return $this->belongsTo(ProjectType::class, 'project_type', 'key');
    }

    public function projectTypeLabel(): string
    {
        $this->loadMissing('projectTypeDefinition');

        return $this->projectTypeDefinition?->label ?: (string) $this->project_type;
    }

    public function supportsBudgetItems(): bool
    {
        $this->loadMissing('projectTypeDefinition');

        return (bool) $this->projectTypeDefinition?->supports_budget_items;
    }
}
