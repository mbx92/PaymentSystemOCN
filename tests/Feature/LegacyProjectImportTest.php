<?php

namespace Tests\Feature;

use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\User;
use App\Services\LegacyProjectImportService;
use App\Services\LegacyProjectSalesQcService;
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

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);
    }
}
