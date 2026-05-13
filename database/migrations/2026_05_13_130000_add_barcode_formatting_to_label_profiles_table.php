<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_profiles', function (Blueprint $table) {
            $table->string('barcode_type', 16)->default('code128')->after('protocol');
            $table->unsignedTinyInteger('barcode_width')->default(1)->after('barcode_type');
        });

        DB::table('label_profiles')
            ->where('width_mm', 33)
            ->where('height_mm', 15)
            ->update([
                'barcode_type' => 'ean13',
                'barcode_width' => 2,
            ]);
    }

    public function down(): void
    {
        Schema::table('label_profiles', function (Blueprint $table) {
            $table->dropColumn(['barcode_type', 'barcode_width']);
        });
    }
};
