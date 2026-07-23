<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_inventory_records', function (Blueprint $table) {
            $table->decimal('unit_price', 18, 2)->nullable()->after('qty');
        });

        DB::table('accounting_inventory_records')
            ->orderBy('id')
            ->each(function (object $row): void {
                $qty = (float) $row->qty;
                $amount = (float) $row->amount;
                $unitPrice = $qty > 0 ? round($amount / $qty, 2) : $amount;

                DB::table('accounting_inventory_records')
                    ->where('id', $row->id)
                    ->update(['unit_price' => $unitPrice]);
            });
    }

    public function down(): void
    {
        Schema::table('accounting_inventory_records', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
