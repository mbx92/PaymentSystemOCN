<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBudget extends Model
{
    protected $fillable = [
        'name',
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
}
