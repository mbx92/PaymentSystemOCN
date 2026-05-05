<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_out', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('category', 30);
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->text('note')->nullable();
            $table->string('recipient_name')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

        });

        DB::statement("ALTER TABLE cash_out ADD CONSTRAINT chk_cash_out_category CHECK (category IN ('biaya_tim','komisi_referral','operasional','lainnya'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_out');
    }
};
