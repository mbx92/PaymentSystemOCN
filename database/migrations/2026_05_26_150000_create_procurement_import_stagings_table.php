<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_import_stagings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->nullable();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('source_import_key', 191)->unique();
            $table->string('legacy_project_number', 120);
            $table->string('legacy_project_name');
            $table->date('procurement_date');
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('procurement_import_staging_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('procurement_import_staging_id');
            $table->foreign('procurement_import_staging_id', 'fk_procurement_import_staging_lines_stage')
                ->references('id')
                ->on('procurement_import_stagings')
                ->cascadeOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('legacy_item_id', 191)->nullable();
            $table->string('legacy_product_sku', 120)->nullable();
            $table->string('product_name');
            $table->string('unit', 40)->nullable();
            $table->decimal('qty', 18, 2)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_import_staging_lines');
        Schema::dropIfExists('procurement_import_stagings');
    }
};
