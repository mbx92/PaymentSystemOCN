<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('alt_text', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
    }
};
