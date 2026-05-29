<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 3.1 Accounting: Journal Entry soft delete/void columns
        if (! Schema::hasColumn('journal_entries', 'deleted_at')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->softDeletes();
                $table->timestamp('voided_at')->nullable()->after('deleted_at');
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete()->after('voided_at');
                $table->string('void_reason', 500)->nullable()->after('voided_by');
            });
        }

        // 3.3 Inventory: Warehouse soft delete
        if (! Schema::hasColumn('warehouses', 'deleted_at')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 3.1 Accounting: Cash In/Out company_id
        if (! Schema::hasColumn('cash_in', 'company_id')) {
            Schema::table('cash_in', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('cash_out', 'company_id')) {
            Schema::table('cash_out', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            });
        }

        // 3.6 Reporting: Composite indexes
        $this->safeIndex('journal_entries', ['entry_date', 'company_id'], 'idx_journal_entries_date_company');
        $this->safeIndex('journal_lines', ['journal_entry_id', 'account_id'], 'idx_journal_lines_entry_account');

        // 3.2 Purchasing: Search indexes
        $this->safeIndex('vendors', ['code', 'name', 'phone'], 'idx_vendors_search');
        $this->safeIndex('purchase_orders', ['number'], 'idx_po_number');
        $this->safeIndex('goods_receipts', ['number'], 'idx_gr_number');

        // 3.4 CRM: Search index
        $this->safeIndex('crm_customers', ['name', 'company', 'code', 'email', 'phone'], 'idx_crm_customers_search');

        // 3.11 Project: Search index
        $this->safeIndex('projects', ['name', 'client_name'], 'idx_projects_search');

        // 3.13 CMS: IP anonymization column
        if (! Schema::hasColumn('cms_access_logs', 'ip_anonymized')) {
            Schema::table('cms_access_logs', function (Blueprint $table) {
                $table->boolean('ip_anonymized')->default(false)->after('ip_address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journal_entries', 'deleted_at')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropSoftDeletes();
                $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
            });
        }

        if (Schema::hasColumn('warehouses', 'deleted_at')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('cash_in', 'company_id')) {
            Schema::table('cash_in', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }

        if (Schema::hasColumn('cash_out', 'company_id')) {
            Schema::table('cash_out', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }

        $this->safeDropIndex('journal_entries', 'idx_journal_entries_date_company');
        $this->safeDropIndex('journal_lines', 'idx_journal_lines_entry_account');
        $this->safeDropIndex('vendors', 'idx_vendors_search');
        $this->safeDropIndex('purchase_orders', 'idx_po_number');
        $this->safeDropIndex('goods_receipts', 'idx_gr_number');
        $this->safeDropIndex('crm_customers', 'idx_crm_customers_search');
        $this->safeDropIndex('projects', 'idx_projects_search');

        if (Schema::hasColumn('cms_access_logs', 'ip_anonymized')) {
            Schema::table('cms_access_logs', function (Blueprint $table) {
                $table->dropColumn('ip_anonymized');
            });
        }
    }

    private function safeIndex(string $table, array $columns, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $name): void {
                $t->index($columns, $name);
            });
        } catch (\Throwable) {
            // index may already exist
        }
    }

    private function safeDropIndex(string $table, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($name): void {
                $t->dropIndex($name);
            });
        } catch (\Throwable) {
            // index may not exist
        }
    }
};
