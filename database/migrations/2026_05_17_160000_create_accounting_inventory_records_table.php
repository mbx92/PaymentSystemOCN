<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_inventory_records', function (Blueprint $table): void {
            $table->id();
            $table->string('item_name');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('amount', 18, 2);
            $table->date('acquisition_date');
            $table->foreignId('asset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained('accounts')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('acquisition_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_inventory_records');
    }
};
