<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('ref')->unique();
            $table->string('sheet_key', 50)->index();
            $table->string('sheet_label', 100);
            $table->string('supplier_name', 150);
            $table->string('code', 100);
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->decimal('supplier_price', 15, 2)->default(0);
            $table->decimal('last_price', 15, 2)->nullable();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamps();

            $table->index(['sheet_key', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_catalog_items');
    }
};
