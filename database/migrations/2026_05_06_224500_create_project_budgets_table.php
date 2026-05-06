<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_name');
            $table->string('client_contact')->nullable();
            $table->string('project_type', 50)->default('system_website_development');
            $table->decimal('estimated_value', 18, 2)->default(0);
            $table->json('cctv_items')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('deal_at')->nullable();
            $table->uuid('converted_project_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budgets');
    }
};

