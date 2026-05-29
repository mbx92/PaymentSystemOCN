<?php

namespace App\Models;

use App\ERP\CRM\Models\CrmCustomer;
use App\ERP\Shared\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Project extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'client_name',
        'client_contact',
        'crm_customer_id',
        'project_type',
        'total_value',
        'status',
        'invoice_number',
        'invoiced_at',
        'document_status',
        'approved_at',
        'approved_by',
        'posted_at',
        'posted_by',
        'started_at',
        'finished_at',
        'description',
        'legal_vault_path',
        'import_key',
        'team_distribution_rate',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'started_at' => 'date',
        'finished_at' => 'date',
        'team_distribution_rate' => 'decimal:2',
    ];

    public function payments()
    {
        return $this->hasMany(ProjectPayment::class)->orderBy('term_number');
    }

    public function crmCustomer()
    {
        return $this->belongsTo(CrmCustomer::class, 'crm_customer_id');
    }

    public function projectTypeDefinition()
    {
        return $this->belongsTo(ProjectType::class, 'project_type', 'key');
    }

    public function cashIns()
    {
        return $this->hasMany(CashIn::class);
    }

    public function cashOuts()
    {
        return $this->hasMany(CashOut::class);
    }

    public function teamDistributions()
    {
        return $this->hasMany(TeamDistribution::class);
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class)->orderBy('sort_order')->orderBy('id');
    }

    public function materials()
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    public function convertedBudget()
    {
        return $this->hasOne(ProjectBudget::class, 'converted_project_id');
    }

    /**
     * Nilai kontrak untuk tampilan daftar: kolom project, lalu item budget hasil convert, lalu total harga material.
     */
    public function resolveListTotalValue(): float
    {
        $this->loadMissing(['convertedBudget.items', 'materials.product']);

        if ((float) $this->total_value > 0) {
            return (float) $this->total_value;
        }

        $budget = $this->convertedBudget;

        if ($budget) {
            $items = $budget->relationLoaded('items') ? $budget->items : collect();

            if ($items->isNotEmpty()) {
                $fromItems = (float) $items->sum(
                    fn ($item) => (float) $item->qty * (float) $item->unit_price
                );

                if ($fromItems > 0) {
                    return $fromItems;
                }
            }

            if ((float) $budget->estimated_value > 0) {
                return (float) $budget->estimated_value;
            }
        }

        $fromMaterials = (float) $this->materials->sum(
            fn ($material) => (float) $material->planned_qty * (float) $material->unit_price
        );

        if ($fromMaterials > 0) {
            return $fromMaterials;
        }

        return 0.0;
    }

    /**
     * Nilai tagihan invoice: kolom total_value project, atau turunan budget/material.
     */
    public function resolveInvoiceAmount(): float
    {
        return $this->resolveListTotalValue();
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

    public function supportsProjectBoard(): bool
    {
        $this->loadMissing('projectTypeDefinition');

        return (bool) $this->projectTypeDefinition?->supports_project_board;
    }

    /**
     * Baris detail nota/invoice: item budget hasil convert, lalu material project, lalu legacy cctv_items.
     *
     * @return Collection<int, array{name: string, description: string, qty: float, uom: string, unit_price: float, subtotal: float}>
     */
    public function resolveInvoiceLineItems(): Collection
    {
        $this->loadMissing(['materials.product', 'convertedBudget.items']);

        $budget = $this->convertedBudget;
        $budgetItems = $budget?->items ?? collect();

        if ($budgetItems->isNotEmpty()) {
            return $budgetItems->map(fn ($item): array => [
                'name' => (string) $item->name,
                'description' => trim(collect([$item->item_type, $item->notes])->filter()->implode(' · ')) ?: 'Item budget project',
                'qty' => (float) $item->qty,
                'uom' => (string) ($item->uom ?: 'unit'),
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->qty * (float) $item->unit_price,
            ])->values();
        }

        $materialItems = $this->materials
            ->filter(fn ($material) => (float) $material->planned_qty > 0)
            ->map(function ($material): array {
                $qty = (float) $material->planned_qty;
                $unitPrice = (float) $material->unit_price;
                if ($unitPrice <= 0) {
                    $unitPrice = (float) ($material->product?->selling_price ?? 0);
                }

                return [
                    'name' => (string) ($material->product?->name ?? 'Material project'),
                    'description' => trim(implode(' · ', array_filter([
                        $material->product?->sku,
                        $material->notes,
                    ]))) ?: 'Material project',
                    'qty' => $qty,
                    'uom' => (string) ($material->product?->uom ?? 'unit'),
                    'unit_price' => $unitPrice,
                    'subtotal' => $qty * $unitPrice,
                ];
            })
            ->values();

        if ($materialItems->isNotEmpty()) {
            return $materialItems;
        }

        $legacyBudgetItems = collect($budget?->cctv_items ?? [])
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['name'] ?? '')) !== '')
            ->map(function (array $item): array {
                $qty = (float) ($item['qty'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);

                return [
                    'name' => (string) $item['name'],
                    'description' => 'Item dari budget project',
                    'qty' => $qty,
                    'uom' => 'unit',
                    'unit_price' => $unitPrice,
                    'subtotal' => $qty * $unitPrice,
                ];
            })
            ->values();

        if ($legacyBudgetItems->isNotEmpty()) {
            return $legacyBudgetItems;
        }

        $amount = $this->resolveInvoiceAmount();

        return collect([[
            'name' => 'Nilai project '.$this->name,
            'description' => $this->description ?: 'Pekerjaan project sesuai kesepakatan.',
            'qty' => 1,
            'uom' => 'project',
            'unit_price' => $amount,
            'subtotal' => $amount,
        ]]);
    }

    public function getTotalCashInAttribute(): float
    {
        return (float) $this->cashIns()->sum('amount');
    }

    public function getTotalCashOutAttribute(): float
    {
        return (float) $this->cashOuts()->sum('amount');
    }

    public function getProfitAttribute(): float
    {
        return $this->total_cash_in - $this->total_cash_out;
    }

    public function getTotalReferralCommissionAttribute(): float
    {
        return (float) $this->referrals()->sum('commission_amount');
    }

    public function getTotalOperationalAttribute(): float
    {
        return (float) $this->cashOuts()->where('category', 'operasional')->sum('amount');
    }

    public function getNetTeamValueAttribute(): float
    {
        return $this->total_value - $this->total_referral_commission - $this->total_operational;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'berjalan');
    }
}
