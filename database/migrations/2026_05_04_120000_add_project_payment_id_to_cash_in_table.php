<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_in', function (Blueprint $table) {
            $table->foreignUuid('project_payment_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_payments')
                ->cascadeOnDelete();
            $table->unique('project_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_in', function (Blueprint $table) {
            $table->dropUnique(['project_payment_id']);
            $table->dropForeign(['project_payment_id']);
            $table->dropColumn('project_payment_id');
        });
    }
};
