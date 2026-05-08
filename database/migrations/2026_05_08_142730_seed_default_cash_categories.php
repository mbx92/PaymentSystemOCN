<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $defaults = [
            // cash_out
            ['domain' => 'cash_out', 'key' => 'biaya_tim', 'label' => 'Biaya Tim', 'sort_order' => 10],
            ['domain' => 'cash_out', 'key' => 'komisi_referral', 'label' => 'Komisi Referral', 'sort_order' => 20],
            ['domain' => 'cash_out', 'key' => 'operasional', 'label' => 'Operasional', 'sort_order' => 30],
            ['domain' => 'cash_out', 'key' => 'lainnya', 'label' => 'Lainnya', 'sort_order' => 90],

            // cash_in
            ['domain' => 'cash_in', 'key' => 'pendapatan_jasa', 'label' => 'Pendapatan Jasa', 'sort_order' => 10],
            ['domain' => 'cash_in', 'key' => 'lainnya', 'label' => 'Lainnya', 'sort_order' => 90],
        ];

        foreach ($defaults as $row) {
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
        DB::table('cash_categories')->whereIn('domain', ['cash_out', 'cash_in'])->delete();
    }
};

