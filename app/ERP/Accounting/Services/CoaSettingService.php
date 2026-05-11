<?php

namespace App\ERP\Accounting\Services;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\CoaSetting;

class CoaSettingService
{
    public function resolveAccountByKey(string $key, string $fallbackCode): Account
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

        return Account::query()->where('code', $fallbackCode)->firstOrFail();
    }
}

