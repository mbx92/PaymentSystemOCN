<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 4.11 Personal Finance: Soft delete transactions
        if (! Schema::hasColumn('personal_transactions', 'deleted_at')) {
            Schema::table('personal_transactions', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('personal_transactions', 'deleted_at')) {
            Schema::table('personal_transactions', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
