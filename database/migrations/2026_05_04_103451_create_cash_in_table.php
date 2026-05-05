<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_in', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('category', 30)->default('pendapatan_jasa');
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

        });

        DB::statement("ALTER TABLE cash_in ADD CONSTRAINT chk_cash_in_category CHECK (category IN ('pendapatan_jasa','lainnya'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_in');
    }
};
