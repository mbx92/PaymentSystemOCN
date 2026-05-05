<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('document_status', 20)->default('draft')->after('status');
            $table->timestamp('approved_at')->nullable()->after('document_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('approved_by');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('project_payments', function (Blueprint $table) {
            $table->string('document_status', 20)->default('draft')->after('amount');
            $table->timestamp('approved_at')->nullable()->after('document_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('approved_by');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('cash_in', function (Blueprint $table) {
            $table->string('document_status', 20)->default('draft')->after('amount');
            $table->timestamp('approved_at')->nullable()->after('document_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('approved_by');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->after('posted_by')->constrained('journal_entries')->nullOnDelete();
        });

        Schema::table('cash_out', function (Blueprint $table) {
            $table->string('document_status', 20)->default('draft')->after('amount');
            $table->timestamp('approved_at')->nullable()->after('document_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('approved_by');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->after('posted_by')->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_out', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_entry_id');
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn('posted_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropColumn('document_status');
        });

        Schema::table('cash_in', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_entry_id');
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn('posted_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropColumn('document_status');
        });

        Schema::table('project_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn('posted_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropColumn('document_status');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn('posted_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropColumn('document_status');
        });
    }
};
