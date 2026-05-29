<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_product_uom_mappings', function (Blueprint $table) {
            $table->boolean('use_auto_price')->default(true)->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('master_product_uom_mappings', function (Blueprint $table) {
            $table->dropColumn('use_auto_price');
        });
    }
};
