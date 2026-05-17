<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cash_categories')
            ->where(function ($query) {
                $query
                    ->where(fn ($q) => $q->where('domain', 'cash_in')->where('key', 'dana_material_client'))
                    ->orWhere(fn ($q) => $q->where('domain', 'cash_out')->where('key', 'pemakaian_dana_material_client'));
            })
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('cash_categories')
            ->where(function ($query) {
                $query
                    ->where(fn ($q) => $q->where('domain', 'cash_in')->where('key', 'dana_material_client'))
                    ->orWhere(fn ($q) => $q->where('domain', 'cash_out')->where('key', 'pemakaian_dana_material_client'));
            })
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
