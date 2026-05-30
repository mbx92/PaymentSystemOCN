<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_investments', function (Blueprint $table) {
            $table->string('ticker', 32)->nullable()->after('name');
            $table->decimal('units_held', 18, 4)->default(0)->after('is_active');
            $table->decimal('current_price', 15, 2)->nullable()->after('units_held');
            $table->decimal('previous_close', 15, 2)->nullable()->after('current_price');
            $table->decimal('price_change', 15, 2)->nullable()->after('previous_close');
            $table->decimal('price_change_percent', 8, 4)->nullable()->after('price_change');
            $table->timestamp('last_synced_at')->nullable()->after('price_change_percent');
        });
    }

    public function down(): void
    {
        Schema::table('personal_investments', function (Blueprint $table) {
            $table->dropColumn([
                'ticker',
                'units_held',
                'current_price',
                'previous_close',
                'price_change',
                'price_change_percent',
                'last_synced_at',
            ]);
        });
    }
};
