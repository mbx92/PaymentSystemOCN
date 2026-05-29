<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->softDeletes();
        });

        if (! Schema::hasColumn('fiscal_periods', 'company_id')) {
            Schema::table('fiscal_periods', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
                $table->index(['company_id', 'start_date', 'end_date']);
            });
        }

        if (! Schema::hasColumn('document_sequences', 'company_id')) {
            Schema::table('document_sequences', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
                $table->dropUnique(['module', 'document_type']);
                $table->unique(['module', 'document_type', 'company_id']);
            });
        }

        if (! Schema::hasColumn('tax_configurations', 'company_id')) {
            Schema::table('tax_configurations', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('audit_trails', function (Blueprint $table) {
            $table->index(['actor_id', 'created_at']);
        });

        if (! Schema::hasColumn('master_products', 'company_id')) {
            Schema::table('master_products', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
                $table->index(['low_stock_alert_enabled', 'stock', 'min_stock']);
            });
        }

        if (! Schema::hasColumn('employees', 'company_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
                $table->softDeletes();
            });
        }

        Schema::table('vendors', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropSoftDeletes();
            if (Schema::hasColumn('employees', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropIndex(['low_stock_alert_enabled', 'stock', 'min_stock']);
            if (Schema::hasColumn('master_products', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->dropIndex(['actor_id', 'created_at']);
        });
        Schema::table('tax_configurations', function (Blueprint $table) {
            if (Schema::hasColumn('tax_configurations', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
        Schema::table('document_sequences', function (Blueprint $table) {
            if (Schema::hasColumn('document_sequences', 'company_id')) {
                $table->dropUnique(['module', 'document_type', 'company_id']);
                $table->unique(['module', 'document_type']);
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
        Schema::table('fiscal_periods', function (Blueprint $table) {
            if (Schema::hasColumn('fiscal_periods', 'company_id')) {
                $table->dropIndex(['company_id', 'start_date', 'end_date']);
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
