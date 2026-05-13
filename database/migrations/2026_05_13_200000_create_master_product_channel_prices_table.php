<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_product_channel_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_product_id')->constrained('master_products')->cascadeOnDelete();
            $table->string('sales_channel', 50);
            $table->string('label', 100)->nullable();
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['master_product_id', 'sales_channel'], 'product_channel_price_unique');
            $table->index(['sales_channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_channel_prices');
    }
};
