<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_budgets', function (Blueprint $table) {
            $table->foreignId('crm_customer_id')
                ->nullable()
                ->after('name')
                ->constrained('crm_customers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_customer_id');
        });
    }
};
