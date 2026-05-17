<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 2)->default(0)->after('issued_qty');
            $table->decimal('unit_price', 18, 2)->default(0)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'unit_price']);
        });
    }
};
