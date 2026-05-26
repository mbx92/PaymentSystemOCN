<?php

namespace Tests\Feature;

use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\User;
use App\Services\LegacyProjectImportService;
use App\Services\LegacyProjectSalesQcService;
use App\Services\LegacySupplierImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class LegacyProjectImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_trigger_import_selected_legacy_projects(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->instance(LegacyProjectImportService::class, new class extends LegacyProjectImportService
        {
            public function __construct() {}

            public function importSelected(array $importKeys, int $performedByUserId): array
            {
                return [
                    'created_project_count' => 2,
                    'created_staging_count' => 2,
                    'skipped' => [],
                    'created_projects' => $importKeys,
                ];
            }
        });

        $this->instance(LegacyProjectSalesQcService::class, new class extends LegacyProjectSalesQcService
        {
            public function buildReport(): array
            {
                return [
                    'summary' => [
                        'ready_projects' => 71,
                        'warning_projects' => 1,
                        'blocked_projects' => 2,
                    ],
                    'projects' => [],
                ];
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.legacy-sales.import-selected'), [
                'import_keys' => ['ocn1-project:abc', 'ocn1-project:def'],
            ])
            ->assertRedirect(route('erp.admin.legacy-import'))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                '2 project dibuat'
            ));
    }

    public function test_import_selected_legacy_projects_failure_returns_error_flash(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->instance(LegacyProjectImportService::class, new class extends LegacyProjectImportService
        {
            public function __construct() {}

            public function importSelected(array $importKeys, int $performedByUserId): array
            {
                throw new RuntimeException('legacy import failed');
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.legacy-sales.import-selected'), [
                'import_keys' => ['ocn1-project:abc'],
            ])
            ->assertRedirect(route('erp.admin.legacy-import'))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                'legacy import failed'
            ));
    }

    public function test_admin_can_trigger_import_legacy_suppliers(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->instance(LegacySupplierImportService::class, new class extends LegacySupplierImportService
        {
            public function __construct() {}

            public function importSelected(array $legacyIds, int $performedByUserId): array
            {
                return [
                    'created_count' => 5,
                    'updated_count' => 2,
                    'skipped_count' => 0,
                    'created' => [],
                    'updated' => [],
                    'skipped' => [],
                ];
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.legacy-suppliers.import'), [
                'legacy_ids' => ['cmk19p2lm00088i0458i9rc1l', 'cmk19p2lr00098i04fq9fu4d9'],
            ])
            ->assertRedirect(route('erp.admin.legacy-import'))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                '5 vendor dibuat, 2 vendor diupdate'
            ));
    }

    public function test_import_legacy_suppliers_failure_returns_error_flash(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->instance(LegacySupplierImportService::class, new class extends LegacySupplierImportService
        {
            public function __construct() {}

            public function importSelected(array $legacyIds, int $performedByUserId): array
            {
                throw new RuntimeException('legacy supplier import failed');
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.legacy-suppliers.import'), [
                'legacy_ids' => ['cmk19p2lm00088i0458i9rc1l'],
            ])
            ->assertRedirect(route('erp.admin.legacy-import'))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                'legacy supplier import failed'
            ));
    }

    public function test_import_legacy_suppliers_requires_selection(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.legacy-suppliers.import'), [
                'legacy_ids' => [],
            ])
            ->assertSessionHasErrors(['legacy_ids']);
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);
    }
}
