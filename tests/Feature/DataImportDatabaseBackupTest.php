<?php

namespace Tests\Feature;

use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DataImportDatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_data_import_backup_downloads_pg_dump_file(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);

        $user = User::factory()->create();
        $this->instance(DatabaseBackupService::class, new class extends DatabaseBackupService
        {
            public function downloadPostgresDump(?string $connectionName = null): BinaryFileResponse
            {
                $path = tempnam(sys_get_temp_dir(), 'pg-dump-test-');
                file_put_contents($path, "-- PostgreSQL database dump\nCREATE TABLE users (id bigint primary key);\n");

                return response()->download($path, 'backup-database-test.sql', [
                    'Content-Type' => 'application/sql; charset=UTF-8',
                ])->deleteFileAfterSend(true);
            }
        });

        $response = $this
            ->actingAs($user)
            ->get(route('erp.admin.data-import.backup'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/sql; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $payload = $response->getFile()->getContent();
        $this->assertStringContainsString('PostgreSQL database dump', $payload);
        $this->assertStringContainsString('CREATE TABLE users', $payload);
    }

    public function test_admin_data_import_backup_failure_shows_underlying_reason(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);

        $user = User::factory()->create();
        $this->instance(DatabaseBackupService::class, new class extends DatabaseBackupService
        {
            public function downloadPostgresDump(?string $connectionName = null): BinaryFileResponse
            {
                throw new RuntimeException('pg_dump gagal dijalankan. Detail: connection to server at "127.0.0.1" port 5432 failed.');
            }
        });

        $this
            ->actingAs($user)
            ->get(route('erp.admin.data-import.backup'))
            ->assertRedirect(route('erp.admin.data-import', ['tab' => 'backup']))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                'connection to server at "127.0.0.1" port 5432 failed.'
            ));
    }
}
