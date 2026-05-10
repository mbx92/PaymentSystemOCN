<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_site_id')->unique()->constrained('landing_sites')->cascadeOnDelete();
            $table->string('headline', 190)->nullable();
            $table->string('subheadline', 255)->nullable();
            $table->text('body')->nullable();
            $table->string('primary_cta_text', 80)->nullable();
            $table->string('primary_cta_url', 500)->nullable();
            $table->string('secondary_cta_text', 80)->nullable();
            $table->string('secondary_cta_url', 500)->nullable();
            $table->string('contact_text', 255)->nullable();
            $table->string('seo_title', 190)->nullable();
            $table->string('seo_description', 255)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_site_pages');
    }
};
