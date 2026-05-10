<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('currency', 3)->default('IDR');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('personal_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 16);
            $table->string('color', 16)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'name', 'type']);
        });

        Schema::create('personal_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('personal_wallets')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('personal_categories')->nullOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 15, 2);
            $table->date('occurred_on');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'occurred_on']);
        });

        Schema::create('personal_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('personal_categories')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount_limit', 15, 2);
            $table->timestamps();
            $table->unique(['user_id', 'category_id', 'year', 'month']);
        });

        Schema::create('personal_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('asset_type', 32);
            $table->string('institution', 160)->nullable();
            $table->text('notes')->nullable();
            $table->date('opened_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('personal_investment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('personal_investments')->cascadeOnDelete();
            $table->date('occurred_on');
            $table->string('flow', 16);
            $table->decimal('amount', 15, 2);
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['investment_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_investment_movements');
        Schema::dropIfExists('personal_investments');
        Schema::dropIfExists('personal_budgets');
        Schema::dropIfExists('personal_transactions');
        Schema::dropIfExists('personal_categories');
        Schema::dropIfExists('personal_wallets');
    }
};
