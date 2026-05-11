<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('rows')->default(1)->after('gap_mm');
        });
    }

    public function down(): void
    {
        Schema::table('label_profiles', function (Blueprint $table) {
            $table->dropColumn('rows');
        });
    }
};
