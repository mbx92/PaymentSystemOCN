<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Allow dynamic categories managed via master tables.
        DB::statement("ALTER TABLE cash_out DROP CONSTRAINT IF EXISTS chk_cash_out_category");
        DB::statement("ALTER TABLE cash_in DROP CONSTRAINT IF EXISTS chk_cash_in_category");
    }

    public function down(): void
    {
        // Restore original constraints (static categories).
        DB::statement("ALTER TABLE cash_out ADD CONSTRAINT chk_cash_out_category CHECK (category IN ('biaya_tim','komisi_referral','operasional','lainnya'))");
        DB::statement("ALTER TABLE cash_in ADD CONSTRAINT chk_cash_in_category CHECK (category IN ('pendapatan_jasa','lainnya'))");
    }
};

