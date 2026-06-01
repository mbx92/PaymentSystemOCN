<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_site_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name', 120);
            $table->string('family_layout_key', 32)->index();
            $table->string('scope', 32)->default('system');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->json('schema')->nullable();
            $table->timestamps();
        });

        Schema::create('landing_site_themes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name', 120);
            $table->string('scope', 32)->default('system');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->json('tokens')->nullable();
            $table->timestamps();
        });

        Schema::create('landing_site_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_site_id')->constrained('landing_sites')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('landing_site_templates')->nullOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained('landing_site_themes')->nullOnDelete();
            $table->string('status', 32)->index();
            $table->unsignedInteger('version_no')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->json('theme_overrides')->nullable();
            $table->json('document')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['landing_site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_site_page_versions');
        Schema::dropIfExists('landing_site_themes');
        Schema::dropIfExists('landing_site_templates');
    }
};
