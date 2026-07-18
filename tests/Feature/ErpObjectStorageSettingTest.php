<?php

namespace Tests\Feature;

use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\ErpSetting;
use App\Models\User;
use App\Services\GeneratedFileArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class ErpObjectStorageSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_object_storage_settings_without_resending_secret(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        ErpSetting::query()->create([
            'app_name' => 'OCN ERP Suite',
            'object_storage_enabled' => true,
            'object_storage_access_key' => 'saved-key',
            'object_storage_secret_key' => 'saved-secret',
            'object_storage_bucket' => 'archive-bucket',
            'object_storage_region' => 'us-east-1',
            'object_storage_prefix' => 'erp-archive',
            'object_storage_archive_pdf' => true,
            'object_storage_archive_excel' => true,
            'object_storage_archive_database' => true,
        ]);

        $this->actingAs($user)
            ->post(route('erp.admin.erp-settings.update'), [
                'app_name' => 'OCN ERP Suite',
                'app_tagline' => 'Integrated Business Platform',
                'remove_logo' => false,
                'module_menu_layout' => 'grid',
                'screen_mode' => 'auto',
                'screen_density' => 'comfortable',
                'object_storage_enabled' => true,
                'object_storage_access_key' => 'saved-key',
                'object_storage_secret_key' => '',
                'object_storage_bucket' => 'archive-bucket',
                'object_storage_region' => 'us-east-1',
                'object_storage_endpoint' => 'https://minio.test',
                'object_storage_use_path_style' => true,
                'object_storage_prefix' => 'erp-archive',
                'object_storage_archive_pdf' => true,
                'object_storage_archive_excel' => false,
                'object_storage_archive_database' => true,
            ])
            ->assertRedirect();

        $setting = ErpSetting::query()->firstOrFail();
        $this->assertTrue($setting->object_storage_enabled);
        $this->assertSame('saved-key', $setting->object_storage_access_key);
        $this->assertSame('saved-secret', $setting->resolvedObjectStorageSecretKey());
        $this->assertSame('https://minio.test', $setting->object_storage_endpoint);
        $this->assertTrue($setting->object_storage_use_path_style);
        $this->assertFalse($setting->object_storage_archive_excel);
    }

    public function test_object_storage_test_endpoint_uses_service_result(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $mock = \Mockery::mock(GeneratedFileArchiveService::class);
        $mock->shouldReceive('testConnection')
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'mock ok',
            ]);
        $this->app->instance(GeneratedFileArchiveService::class, $mock);

        $this->actingAs($user)
            ->postJson(route('erp.admin.erp-settings.object-storage.test'), [
                'object_storage_access_key' => 'key',
                'object_storage_secret_key' => 'secret',
                'object_storage_bucket' => 'bucket',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'mock ok',
            ]);
    }

    public function test_archive_service_skips_upload_when_disabled(): void
    {
        $service = app(GeneratedFileArchiveService::class);
        $path = $service->archiveContent('hello', GeneratedFileArchiveService::CATEGORY_PDF, 'test.pdf', 'application/pdf');

        $this->assertNull($path);
    }

    public function test_archive_service_uploads_when_enabled(): void
    {
        Storage::fake('erp_archive');

        ErpSetting::query()->create([
            'app_name' => 'OCN ERP Suite',
            'object_storage_enabled' => true,
            'object_storage_access_key' => 'test-key',
            'object_storage_secret_key' => 'test-secret',
            'object_storage_bucket' => 'archive-bucket',
            'object_storage_region' => 'us-east-1',
            'object_storage_prefix' => 'erp-archive',
            'object_storage_archive_pdf' => true,
        ]);

        $service = app(GeneratedFileArchiveService::class);
        $path = $service->archiveContent('hello-pdf', GeneratedFileArchiveService::CATEGORY_PDF, 'invoice-test.pdf', 'application/pdf');

        $this->assertNotNull($path);
        Storage::disk('erp_archive')->assertExists($path);
        $this->assertSame('hello-pdf', Storage::disk('erp_archive')->get($path));
    }

    public function test_disk_config_auto_enables_path_style_for_custom_endpoint(): void
    {
        $setting = new ErpSetting([
            'object_storage_enabled' => true,
            'object_storage_access_key' => 'test-key',
            'object_storage_secret_key' => 'test-secret',
            'object_storage_bucket' => 'archive-bucket',
            'object_storage_region' => 'us-east-1',
            'object_storage_endpoint' => 'https://s3.yumalab.my.id/',
            'object_storage_use_path_style' => false,
        ]);

        $config = app(GeneratedFileArchiveService::class)->diskConfig($setting);

        $this->assertSame('https://s3.yumalab.my.id', $config['endpoint']);
        $this->assertTrue($config['use_path_style_endpoint']);
    }

    public function test_disk_config_keeps_virtual_host_style_for_aws_endpoint_when_not_forced(): void
    {
        $setting = new ErpSetting([
            'object_storage_enabled' => true,
            'object_storage_access_key' => 'test-key',
            'object_storage_secret_key' => 'test-secret',
            'object_storage_bucket' => 'archive-bucket',
            'object_storage_region' => 'ap-southeast-1',
            'object_storage_endpoint' => 'https://s3.ap-southeast-1.amazonaws.com/',
            'object_storage_use_path_style' => false,
        ]);

        $config = app(GeneratedFileArchiveService::class)->diskConfig($setting);

        $this->assertSame('https://s3.ap-southeast-1.amazonaws.com', $config['endpoint']);
        $this->assertFalse($config['use_path_style_endpoint']);
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
            RoleOrPermissionMiddleware::class,
        ]);
    }
}
