<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_product_uom_mappings', function (Blueprint $table) {
            $table->string('price_operation', 10)->default('multiply')->after('multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('master_product_uom_mappings', function (Blueprint $table) {
            $table->dropColumn('price_operation');
        });
    }
};
