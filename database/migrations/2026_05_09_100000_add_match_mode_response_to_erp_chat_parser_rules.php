<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_chat_parser_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('erp_chat_parser_rules', 'response_text')) {
                $table->text('response_text')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('erp_chat_parser_rules', 'match_mode')) {
                // 'and' = semua keyword harus ada, 'or' = cukup salah satu
                $table->enum('match_mode', ['and', 'or'])->default('and')->after('keywords');
            }
        });
    }

    public function down(): void
    {
        Schema::table('erp_chat_parser_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('erp_chat_parser_rules', 'match_mode')) {
                $table->dropColumn('match_mode');
            }
        });
    }
};
