<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_sites', function (Blueprint $table) {
            $table->string('layout_key', 32)->default('toko')->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('landing_sites', function (Blueprint $table) {
            $table->dropColumn('layout_key');
        });
    }
};
