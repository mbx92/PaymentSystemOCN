<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_product_warehouse_stocks', function (Blueprint $table) {
            $table->decimal('reserved_qty', 18, 2)->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('master_product_warehouse_stocks', function (Blueprint $table) {
            $table->dropColumn('reserved_qty');
        });
    }
};
