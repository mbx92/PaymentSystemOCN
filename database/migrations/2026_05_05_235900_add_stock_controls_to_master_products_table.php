<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->unsignedInteger('min_stock')->default(0)->after('stock');
            $table->unsignedInteger('total_sold')->default(0)->after('min_stock');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn(['min_stock', 'total_sold']);
        });
    }
};
