<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelf_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shelf_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tier');
            $table->unsignedInteger('slot_position')->default(0);
            $table->foreignId('product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedInteger('min_qty')->default(0);
            $table->timestamps();

            $table->unique(['shelf_id', 'tier', 'slot_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelf_slots');
    }
};
