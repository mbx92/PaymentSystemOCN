<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_settings', function (Blueprint $table) {
            $table->boolean('chatbot_llm_enabled')->default(false)->after('maintenance_modules');
            $table->string('chatbot_llm_api_url', 500)->nullable()->after('chatbot_llm_enabled');
            $table->text('chatbot_llm_api_key')->nullable()->after('chatbot_llm_api_url');
            $table->string('chatbot_llm_model', 120)->nullable()->after('chatbot_llm_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('erp_settings', function (Blueprint $table) {
            $table->dropColumn([
                'chatbot_llm_enabled',
                'chatbot_llm_api_url',
                'chatbot_llm_api_key',
                'chatbot_llm_model',
            ]);
        });
    }
};
