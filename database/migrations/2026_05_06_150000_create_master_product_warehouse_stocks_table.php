<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_product_warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_product_id')->constrained('master_products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('qty', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['master_product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_warehouse_stocks');
    }
};

