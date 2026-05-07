<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_chat_parser_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('intent_key', 60);
            $table->json('keywords');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['intent_key', 'is_active']);
            $table->index(['priority', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_chat_parser_rules');
    }
};
