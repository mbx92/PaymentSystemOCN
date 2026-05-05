<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('client_name');
            $table->string('client_contact')->nullable();
            $table->decimal('total_value', 15, 2);
            $table->string('status', 20)->default('negosiasi');
            $table->date('started_at')->nullable();
            $table->date('finished_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE projects ADD CONSTRAINT chk_projects_status CHECK (status IN ('negosiasi','berjalan','selesai','dibatalkan'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
