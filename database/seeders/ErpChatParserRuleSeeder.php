<?php

namespace Database\Seeders;

use App\Models\ErpChatParserRule;
use Illuminate\Database\Seeder;

class ErpChatParserRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'name' => 'Lookup stok produk',
                'intent_key' => 'stock_lookup',
                'keywords' => ['stok'],
                'priority' => 10,
                'is_active' => true,
                'notes' => 'Intent untuk cek sisa stok produk.',
            ],
            [
                'name' => 'Lookup stok barang',
                'intent_key' => 'stock_lookup',
                'keywords' => ['stock'],
                'priority' => 11,
                'is_active' => true,
                'notes' => 'Sinonim bahasa Inggris untuk stok.',
            ],
            [
                'name' => 'Harga produk',
                'intent_key' => 'product_price_lookup',
                'keywords' => ['harga'],
                'priority' => 20,
                'is_active' => true,
                'notes' => 'Intent untuk cek harga produk.',
            ],
            [
                'name' => 'Unpaid invoice project',
                'intent_key' => 'invoice_unpaid_list',
                'keywords' => ['invoice', 'belum dibayar'],
                'priority' => 30,
                'is_active' => true,
                'notes' => 'Daftar invoice project dengan status belum dibayar.',
            ],
            [
                'name' => 'Invoice jatuh tempo',
                'intent_key' => 'invoice_due_list',
                'keywords' => ['invoice', 'jatuh tempo'],
                'priority' => 31,
                'is_active' => true,
                'notes' => 'Daftar invoice yang mendekati/melewati jatuh tempo.',
            ],
            [
                'name' => 'Penjualan POS hari ini',
                'intent_key' => 'pos_sales_today',
                'keywords' => ['pos', 'hari ini'],
                'priority' => 40,
                'is_active' => true,
                'notes' => 'Ringkasan transaksi POS harian.',
            ],
            [
                'name' => 'Fallback help',
                'intent_key' => 'help',
                'keywords' => ['bantuan'],
                'priority' => 999,
                'is_active' => true,
                'notes' => 'Fallback sederhana untuk menampilkan kemampuan chatbot.',
            ],
        ];

        foreach ($rules as $rule) {
            ErpChatParserRule::query()->updateOrCreate(
                ['name' => $rule['name'], 'intent_key' => $rule['intent_key']],
                $rule
            );
        }
    }
}
