<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            ['domain' => 'cash_in', 'key' => 'pendapatan_project', 'label' => 'Pendapatan Project', 'sort_order' => 5],
            ['domain' => 'cash_in', 'key' => 'penjualan_pos', 'label' => 'Penjualan POS', 'sort_order' => 15],
            ['domain' => 'cash_out', 'key' => 'refund_penjualan_pos', 'label' => 'Refund Penjualan POS', 'sort_order' => 15],
        ];

        foreach ($rows as $row) {
            DB::table('cash_categories')->updateOrInsert(
                ['domain' => $row['domain'], 'key' => $row['key']],
                [
                    'label' => $row['label'],
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('cash_categories')
            ->whereIn('key', ['pendapatan_project', 'penjualan_pos', 'refund_penjualan_pos'])
            ->delete();
    }
};
