<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('contract_number', 120);
            $table->date('contract_date');
            $table->string('contract_type', 50)->default('website');
            $table->json('pihak_pertama');
            $table->json('pihak_kedua');
            $table->json('pasals');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_contracts');
    }
};
