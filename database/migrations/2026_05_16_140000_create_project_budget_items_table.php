<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_budget_id')->constrained('project_budgets')->cascadeOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->string('item_type', 30)->default('material');
            $table->string('name');
            $table->string('uom', 30)->nullable();
            $table->decimal('qty', 18, 2)->default(1);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_items');
    }
};
