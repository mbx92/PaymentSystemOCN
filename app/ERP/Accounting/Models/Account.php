<?php

namespace App\ERP\Accounting\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

class Account extends Model
{
    /** @var list<string> */
    public const CASH_BANK_SETTING_KEYS = [
        'pos_sale_cash_account',
        'project_invoice_cash_account',
    ];

    /**
     * Akun kas/bank untuk dropdown penerimaan/pembayaran (flag is_cash_bank di CoA).
     */
    public static function cashBankOptions(): Collection
    {
        return static::query()
            ->where('is_cash_bank', true)
            ->where('is_active', true)
            ->where('type', 'asset')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @return array<int, mixed>
     */
    public static function cashBankIdValidationRules(): array
    {
        $ids = static::cashBankOptions()->pluck('id')->all();

        if ($ids === []) {
            return ['required', 'integer', 'exists:accounts,id'];
        }

        return ['required', 'integer', Rule::in($ids)];
    }

    public static function defaultCashBankAccount(): ?self
    {
        $coa = app(\App\ERP\Accounting\Services\CoaSettingService::class);

        try {
            return $coa->resolveAccountByKey('project_invoice_cash_account');
        } catch (\Throwable) {
            return static::cashBankOptions()->first();
        }
    }

    /**
     * Akun aset untuk pencatatan inventaris (peralatan, kendaraan, persediaan, dll.).
     */
    public static function inventoryAssetOptions(): Collection
    {
        return static::query()
            ->where('type', 'asset')
            ->where('normal_balance', 'debit')
            ->where('is_active', true)
            ->where('is_cash_bank', false)
            ->where('name', 'not like', '%Akumulasi%')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @return array<int, mixed>
     */
    public static function inventoryAssetIdValidationRules(): array
    {
        $ids = static::inventoryAssetOptions()->pluck('id')->all();

        if ($ids === []) {
            return ['required', 'integer', 'exists:accounts,id'];
        }

        return ['required', 'integer', Rule::in($ids)];
    }

    public static function defaultInventoryAssetAccount(): ?self
    {
        $peralatan = static::query()
            ->where('is_active', true)
            ->where('code', '1401')
            ->first();

        if ($peralatan) {
            return $peralatan;
        }

        return static::query()
            ->where('is_active', true)
            ->where('type', 'asset')
            ->where('normal_balance', 'debit')
            ->where('is_cash_bank', false)
            ->where('name', 'like', '%Peralatan%')
            ->where('name', 'not like', '%Akumulasi%')
            ->orderBy('code')
            ->first()
            ?? static::inventoryAssetOptions()->first();
    }

    public function displayLabel(): string
    {
        return $this->code.' - '.$this->name;
    }

    protected $fillable = [
        'code',
        'name',
        'type',
        'normal_balance',
        'is_active',
        'is_cash_bank',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'is_cash_bank' => 'bool',
        ];
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
