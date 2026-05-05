<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->string('category', 100);
            $table->string('uom', 20);
            $table->string('sales_channel', 20); // pos, project, both
            $table->string('product_type', 30); // finished_goods, project_material
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_products');
    }
};
