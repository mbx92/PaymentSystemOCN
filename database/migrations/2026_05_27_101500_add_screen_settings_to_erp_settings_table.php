<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_settings', function (Blueprint $table) {
            $table->string('screen_mode', 40)->default('auto')->after('module_menu_layout');
            $table->string('screen_density', 20)->default('comfortable')->after('screen_mode');
        });
    }

    public function down(): void
    {
        Schema::table('erp_settings', function (Blueprint $table) {
            $table->dropColumn(['screen_mode', 'screen_density']);
        });
    }
};
