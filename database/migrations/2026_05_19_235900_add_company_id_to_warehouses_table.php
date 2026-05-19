<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('code')->constrained('companies')->nullOnDelete();
        });

        $companies = DB::table('companies')->get(['id', 'name', 'legal_name']);
        $companyKeywords = $companies->map(function ($company): array {
            $haystack = strtoupper(trim((string) ($company->name ?? '').' '.(string) ($company->legal_name ?? '')));
            $keywords = collect(preg_split('/[^A-Z0-9]+/', $haystack) ?: [])
                ->filter(fn ($part) => strlen((string) $part) >= 3)
                ->unique()
                ->values();

            return [
                'id' => (int) $company->id,
                'keywords' => $keywords,
            ];
        });

        DB::table('warehouses')->orderBy('id')->get(['id', 'code', 'name'])->each(function ($warehouse) use ($companyKeywords): void {
            $haystack = strtoupper(trim((string) $warehouse->code.' '.(string) $warehouse->name));
            $matches = $companyKeywords
                ->filter(fn ($company) => $company['keywords']->contains(fn ($keyword) => Str::contains($haystack, $keyword)))
                ->values();

            if ($matches->count() !== 1) {
                return;
            }

            DB::table('warehouses')
                ->where('id', $warehouse->id)
                ->update(['company_id' => $matches->first()['id']]);
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
