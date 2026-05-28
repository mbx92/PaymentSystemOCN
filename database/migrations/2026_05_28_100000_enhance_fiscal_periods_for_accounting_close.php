<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            $table->string('period_type', 16)->default('monthly')->after('name');
            $table->unsignedSmallInteger('period_year')->default(0)->after('period_type');
            $table->unsignedTinyInteger('period_month')->default(0)->after('period_year');
            $table->text('notes')->nullable()->after('closed_by');
            $table->index(['company_id', 'period_year'], 'fiscal_periods_company_year_idx');
            $table->index(['company_id', 'is_closed', 'start_date', 'end_date'], 'fiscal_periods_company_closed_range_idx');
            $table->unique(['company_id', 'period_type', 'period_year', 'period_month'], 'fiscal_periods_scope_unique');
        });

        DB::table('fiscal_periods')
            ->orderBy('id')
            ->get()
            ->each(function (object $period): void {
                $start = Carbon::parse($period->start_date);
                $end = Carbon::parse($period->end_date);
                $isYearly = $start->isSameDay($start->copy()->startOfYear()) && $end->isSameDay($end->copy()->endOfYear());

                DB::table('fiscal_periods')
                    ->where('id', $period->id)
                    ->update([
                        'period_type' => $isYearly ? 'yearly' : 'monthly',
                        'period_year' => (int) $start->year,
                        'period_month' => $isYearly ? 0 : (int) $start->month,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->dropUnique('fiscal_periods_scope_unique');
            $table->dropIndex('fiscal_periods_company_year_idx');
            $table->dropIndex('fiscal_periods_company_closed_range_idx');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['period_type', 'period_year', 'period_month', 'notes']);
        });
    }
};
