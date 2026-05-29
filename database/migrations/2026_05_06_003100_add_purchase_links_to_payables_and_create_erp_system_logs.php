<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('vendor_id')->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->after('purchase_order_id')->constrained('goods_receipts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->after('status')->constrained('journal_entries')->nullOnDelete();
        });

        Schema::create('erp_system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 40)->default('app');
            $table->string('level', 20)->default('info');
            $table->string('event', 120);
            $table->text('message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 64)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['channel', 'level']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_system_logs');

        Schema::table('payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
            $table->dropConstrainedForeignId('goods_receipt_id');
            $table->dropConstrainedForeignId('journal_entry_id');
        });
    }
};
