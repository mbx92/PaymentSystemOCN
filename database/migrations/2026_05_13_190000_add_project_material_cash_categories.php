<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $accounts = [
            ['code' => '2005', 'name' => 'Pendapatan Diterima Dimuka', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2006', 'name' => 'Dana Titipan Material Client', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '4001', 'name' => 'Pendapatan Jasa', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4002', 'name' => 'Pendapatan Penjualan POS', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4003', 'name' => 'Pendapatan Project', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4004', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4005', 'name' => 'Diskon Penjualan', 'type' => 'revenue', 'normal_balance' => 'debit'],
            ['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5002', 'name' => 'Beban Gaji & Upah', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5009', 'name' => 'HPP - Harga Pokok Penjualan', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5013', 'name' => 'Beban Lain-lain', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5014', 'name' => 'Beban Marketing & Iklan', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->updateOrInsert(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'normal_balance' => $account['normal_balance'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $categories = [
            ['domain' => 'cash_in', 'key' => 'pendapatan_project', 'label' => 'Pendapatan Project', 'sort_order' => 5],
            ['domain' => 'cash_in', 'key' => 'uang_muka_project', 'label' => 'Uang Muka Project', 'sort_order' => 8],
            ['domain' => 'cash_in', 'key' => 'dana_material_client', 'label' => 'Dana Material dari Client', 'sort_order' => 9],
            ['domain' => 'cash_out', 'key' => 'pembelian_material_project', 'label' => 'Pembelian Material Project', 'sort_order' => 25],
            ['domain' => 'cash_out', 'key' => 'pemakaian_dana_material_client', 'label' => 'Pemakaian Dana Material Client', 'sort_order' => 26],
        ];

        foreach ($categories as $category) {
            DB::table('cash_categories')->updateOrInsert(
                ['domain' => $category['domain'], 'key' => $category['key']],
                [
                    'label' => $category['label'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $mappings = [
            ['domain' => 'cash_in', 'category' => 'pendapatan_project', 'account_code' => '4003'],
            ['domain' => 'cash_in', 'category' => 'uang_muka_project', 'account_code' => '2005'],
            ['domain' => 'cash_in', 'category' => 'dana_material_client', 'account_code' => '2006'],
            ['domain' => 'cash_in', 'category' => 'pendapatan_jasa', 'account_code' => '4001'],
            ['domain' => 'cash_in', 'category' => 'penjualan_pos', 'account_code' => '4002'],
            ['domain' => 'cash_in', 'category' => 'pendapatan_pos', 'account_code' => '4002'],
            ['domain' => 'cash_in', 'category' => 'lainnya', 'account_code' => '4004'],
            ['domain' => 'cash_out', 'category' => 'pembelian_material_project', 'account_code' => '5009'],
            ['domain' => 'cash_out', 'category' => 'pemakaian_dana_material_client', 'account_code' => '2006'],
            ['domain' => 'cash_out', 'category' => 'biaya_tim', 'account_code' => '5002'],
            ['domain' => 'cash_out', 'category' => 'komisi_referral', 'account_code' => '5014'],
            ['domain' => 'cash_out', 'category' => 'operasional', 'account_code' => '5001'],
            ['domain' => 'cash_out', 'category' => 'lainnya', 'account_code' => '5013'],
            ['domain' => 'cash_out', 'category' => 'refund_penjualan_pos', 'account_code' => '4005'],
        ];

        foreach ($mappings as $mapping) {
            $accountId = DB::table('accounts')->where('code', $mapping['account_code'])->value('id');
            if (! $accountId) {
                continue;
            }

            DB::table('category_coa_mappings')->updateOrInsert(
                ['domain' => $mapping['domain'], 'category' => $mapping['category']],
                ['account_id' => $accountId, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('category_coa_mappings')
            ->whereIn('category', [
                'uang_muka_project',
                'dana_material_client',
                'pembelian_material_project',
                'pemakaian_dana_material_client',
            ])
            ->delete();

        DB::table('cash_categories')
            ->whereIn('key', [
                'uang_muka_project',
                'dana_material_client',
                'pembelian_material_project',
                'pemakaian_dana_material_client',
            ])
            ->delete();

        DB::table('accounts')->where('code', '2006')->delete();
    }
};
