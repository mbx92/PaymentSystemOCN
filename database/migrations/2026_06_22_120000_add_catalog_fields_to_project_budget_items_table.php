<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_budget_items', function (Blueprint $table) {
            $table->string('catalog_sheet', 50)->nullable()->after('master_product_id');
            $table->string('catalog_ref', 100)->nullable()->after('catalog_sheet');
            $table->string('catalog_category', 100)->nullable()->after('catalog_ref');
        });
    }

    public function down(): void
    {
        Schema::table('project_budget_items', function (Blueprint $table) {
            $table->dropColumn(['catalog_sheet', 'catalog_ref', 'catalog_category']);
        });
    }
};
