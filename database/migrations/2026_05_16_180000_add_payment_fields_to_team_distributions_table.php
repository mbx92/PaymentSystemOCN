<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_distributions', function (Blueprint $table) {
            $table->foreignUuid('cash_out_id')->nullable()->after('total_pay')->constrained('cash_out')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('cash_out_id');
        });
    }

    public function down(): void
    {
        Schema::table('team_distributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_out_id');
            $table->dropColumn('paid_at');
        });
    }
};
