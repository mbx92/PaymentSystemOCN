<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_in', function (Blueprint $table) {
            $table->foreignId('cash_account_id')
                ->nullable()
                ->after('payment_method_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });

        Schema::table('cash_out', function (Blueprint $table) {
            $table->foreignId('cash_account_id')
                ->nullable()
                ->after('project_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_in', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
        });

        Schema::table('cash_out', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
        });
    }
};
