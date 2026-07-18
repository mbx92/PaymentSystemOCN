<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_settings', function (Blueprint $table) {
            $table->boolean('object_storage_enabled')->default(false)->after('screen_density');
            $table->string('object_storage_access_key')->nullable()->after('object_storage_enabled');
            $table->text('object_storage_secret_key')->nullable()->after('object_storage_access_key');
            $table->string('object_storage_bucket')->nullable()->after('object_storage_secret_key');
            $table->string('object_storage_region', 64)->default('us-east-1')->after('object_storage_bucket');
            $table->string('object_storage_endpoint')->nullable()->after('object_storage_region');
            $table->boolean('object_storage_use_path_style')->default(false)->after('object_storage_endpoint');
            $table->string('object_storage_prefix', 120)->default('erp-archive')->after('object_storage_use_path_style');
            $table->boolean('object_storage_archive_pdf')->default(true)->after('object_storage_prefix');
            $table->boolean('object_storage_archive_excel')->default(true)->after('object_storage_archive_pdf');
            $table->boolean('object_storage_archive_database')->default(true)->after('object_storage_archive_excel');
        });
    }

    public function down(): void
    {
        Schema::table('erp_settings', function (Blueprint $table) {
            $table->dropColumn([
                'object_storage_enabled',
                'object_storage_access_key',
                'object_storage_secret_key',
                'object_storage_bucket',
                'object_storage_region',
                'object_storage_endpoint',
                'object_storage_use_path_style',
                'object_storage_prefix',
                'object_storage_archive_pdf',
                'object_storage_archive_excel',
                'object_storage_archive_database',
            ]);
        });
    }
};
