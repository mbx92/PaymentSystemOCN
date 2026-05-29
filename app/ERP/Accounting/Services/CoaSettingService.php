<?php

namespace App\ERP\Accounting\Services;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\CoaSetting;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CoaSettingService
{
    public function resolveAccountByKey(string $key, ?string $fallbackCode = null): Account
    {
        $settingAccountId = CoaSetting::query()
            ->where('key', $key)
            ->value('account_id');

        if ($settingAccountId) {
            $acc = Account::query()->find($settingAccountId);
            if ($acc) {
                return $acc;
            }
        }

        $fallbackCode = $fallbackCode ?? config("accounting.coa_fallback_codes.{$key}");

        if ($fallbackCode) {
            return Account::query()->where('code', $fallbackCode)->firstOrFail();
        }

        $fromCashList = Account::cashBankOptions()->first();
        if ($fromCashList && str_contains($key, 'cash_account')) {
            return $fromCashList;
        }

        throw new ModelNotFoundException(
            "Akun CoA untuk [{$key}] belum diatur di Pengaturan COA."
        );
    }
}
