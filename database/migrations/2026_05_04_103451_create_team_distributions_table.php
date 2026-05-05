<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_distributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('role_in_project', 20);
            $table->decimal('percentage', 5, 2);
            $table->decimal('base_pay', 15, 2);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('total_pay', 15, 2);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        DB::statement("ALTER TABLE team_distributions ADD CONSTRAINT chk_team_role CHECK (role_in_project IN ('lead','developer','designer','qa'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('team_distributions');
    }
};
