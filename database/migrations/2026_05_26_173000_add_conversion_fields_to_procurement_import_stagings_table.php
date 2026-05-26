<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_import_stagings', function (Blueprint $table): void {
            $table->timestamp('converted_at')->nullable()->after('created_by');
            $table->foreignId('converted_by')->nullable()->after('converted_at')->constrained('users')->nullOnDelete();
            $table->json('conversion_summary')->nullable()->after('converted_by');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_import_stagings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('converted_by');
            $table->dropColumn(['converted_at', 'conversion_summary']);
        });
    }
};
