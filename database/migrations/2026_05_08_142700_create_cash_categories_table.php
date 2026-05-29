<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_categories', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 30); // cash_in | cash_out
            $table->string('key', 50);
            $table->string('label', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->unique(['domain', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_categories');
    }
};
