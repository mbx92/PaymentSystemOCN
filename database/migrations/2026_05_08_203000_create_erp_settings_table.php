<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name', 120)->default('OCN ERP Suite');
            $table->string('app_tagline', 190)->nullable();
            $table->string('app_logo_path', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_settings');
    }
};
