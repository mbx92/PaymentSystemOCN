<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_product_uom_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_product_id')->constrained('master_products')->cascadeOnDelete();
            $table->string('uom_code', 20);
            $table->decimal('multiplier', 12, 4)->default(1);
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['master_product_id', 'uom_code']);
            $table->foreign('uom_code')->references('code')->on('uoms')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_uom_mappings');
    }
};

