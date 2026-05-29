<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE team_distributions DROP CONSTRAINT IF EXISTS chk_team_role');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE team_distributions ADD CONSTRAINT chk_team_role CHECK (role_in_project IN ('lead','developer','designer','qa'))");
        }
    }
};
