<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32)->index();
            $table->foreignId('landing_site_id')->nullable()->constrained('landing_sites')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path', 512);
            $table->string('route_name', 128)->nullable();
            $table->string('method', 16)->default('GET');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('country_name', 120)->nullable();
            $table->string('region_name', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('device_type', 24)->default('unknown');
            $table->string('browser', 64)->nullable();
            $table->string('os', 64)->nullable();
            $table->text('referrer')->nullable();
            $table->timestamps();

            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_access_logs');
    }
};
