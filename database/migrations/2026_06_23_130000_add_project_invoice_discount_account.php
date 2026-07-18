<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('accounts')->updateOrInsert(
            ['code' => '4020'],
            [
                'name' => 'Diskon Invoice Project',
                'type' => 'revenue',
                'normal_balance' => 'debit',
                'is_active' => true,
                'is_cash_bank' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $accountId = DB::table('accounts')->where('code', '4020')->value('id');
        if (! $accountId) {
            return;
        }

        DB::table('accounting_coa_settings')->updateOrInsert(
            ['key' => 'project_invoice_discount_account'],
            [
                'account_id' => $accountId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('accounting_coa_settings')
            ->where('key', 'project_invoice_discount_account')
            ->delete();

        DB::table('accounts')->where('code', '4020')->delete();
    }
};
