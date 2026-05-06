<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_materials', function (Blueprint $table) {
            $table->id();
            $table->uuid('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('planned_qty', 18, 2)->default(0);
            $table->decimal('reserved_qty', 18, 2)->default(0);
            $table->decimal('issued_qty', 18, 2)->default(0);
            $table->string('status', 20)->default('reserved');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'master_product_id', 'warehouse_id'], 'uq_project_material_product_wh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_materials');
    }
};

