<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_budgets')) {
            return;
        }

        if (Schema::hasColumn('project_budgets', 'cctv_items')) {
            return;
        }

        Schema::table('project_budgets', function (Blueprint $table) {
            $table->json('cctv_items')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_budgets')) {
            return;
        }

        if (! Schema::hasColumn('project_budgets', 'cctv_items')) {
            return;
        }

        Schema::table('project_budgets', function (Blueprint $table) {
            $table->dropColumn('cctv_items');
        });
    }
};
