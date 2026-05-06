<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE audit_trails ALTER COLUMN auditable_id TYPE VARCHAR(64) USING auditable_id::text');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE audit_trails MODIFY auditable_id VARCHAR(64) NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE audit_trails ALTER COLUMN auditable_id TYPE BIGINT USING NULLIF(auditable_id, \'\')::bigint');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE audit_trails MODIFY auditable_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
