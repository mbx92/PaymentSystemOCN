<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('tax_id', 64)->nullable()->after('address');
            $table->string('payment_terms', 40)->nullable()->after('tax_id');
            $table->unsignedInteger('lead_time_days')->default(7)->after('payment_terms');
            $table->text('notes')->nullable()->after('lead_time_days');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['tax_id', 'payment_terms', 'lead_time_days', 'notes']);
        });
    }
};

