<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('master_products', 'lead_time_days')) {
            return;
        }

        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn('lead_time_days');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('master_products', 'lead_time_days')) {
            return;
        }

        Schema::table('master_products', function (Blueprint $table) {
            $table->unsignedInteger('lead_time_days')->default(7)->after('total_sold');
        });
    }
};
