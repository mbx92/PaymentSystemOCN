<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_coa_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 30); // cash_in | cash_out
            $table->string('category', 50);
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['domain', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_coa_mappings');
    }
};
