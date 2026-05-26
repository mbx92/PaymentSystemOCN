<?php

namespace Tests\Feature;

use App\ERP\Core\Models\Company;
use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Purchasing\Models\Vendor;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\ProcurementImportStaging;
use App\Models\User;
use App\Services\LegacyProjectSalesQcService;
use App\Services\ProcurementImportStagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class ProcurementImportStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_procurement_import_staging(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        [$staging, $vendor] = $this->makeStagingFixture();
        $lineId = $staging->lines()->value('id');

        $this->instance(ProcurementImportStagingService::class, new class extends ProcurementImportStagingService
        {
            public function __construct() {}

            public function updateDraft(ProcurementImportStaging $staging, array $payload): ProcurementImportStaging
            {
                return $staging;
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.procurement-stagings.update', $staging->id), [
                'procurement_date' => '2026-01-07',
                'notes' => 'Update supplier legacy',
                'lines' => [
                    [
                        'id' => $lineId,
                        'vendor_id' => $vendor->id,
                        'qty' => 2,
                        'unit_cost' => 150000,
                    ],
                ],
            ])
            ->assertRedirect(route('erp.admin.legacy-import'))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                'berhasil diperbarui'
            ));
    }

    public function test_admin_can_convert_procurement_import_staging(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        [$staging] = $this->makeStagingFixture();

        $this->instance(ProcurementImportStagingService::class, new class extends ProcurementImportStagingService
        {
            public function __construct() {}

            public function convertToPurchasingDocuments(ProcurementImportStaging $staging, int $performedByUserId): array
            {
                return [
                    'purchase_orders' => [['number' => 'PO-000001']],
                    'goods_receipts' => [['number' => 'GRN-000001']],
                ];
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.procurement-stagings.convert', $staging->id))
            ->assertRedirect(route('erp.admin.legacy-import'))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                '1 PO dan 1 GR'
            ));
    }

    public function test_admin_can_reconcile_procurement_import_stagings_from_legacy_import_workspace(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->instance(LegacyProjectSalesQcService::class, new class extends LegacyProjectSalesQcService
        {
            public function buildReport(): array
            {
                return [
                    'summary' => [
                        'total_projects' => 0,
                    ],
                    'projects' => [],
                ];
            }
        });

        $this->instance(ProcurementImportStagingService::class, new class extends ProcurementImportStagingService
        {
            public function __construct() {}

            public function reconcileOpenStagings(): array
            {
                return [
                    'checked_stagings' => 3,
                    'updated_stagings' => 2,
                    'deleted_stagings' => 1,
                    'removed_service_lines' => 4,
                    'refreshed_product_lines' => 2,
                ];
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.procurement-stagings.reconcile'))
            ->assertRedirect(route('erp.admin.legacy-import'))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                '4 line jasa dihapus'
            ));
    }

    public function test_admin_can_reconcile_procurement_import_staging_for_specific_legacy_project_detail(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->instance(ProcurementImportStagingService::class, new class extends ProcurementImportStagingService
        {
            public function __construct() {}

            public function reconcileStagingsForImportKey(string $sourceImportKey): array
            {
                return [
                    'checked_stagings' => 1,
                    'updated_stagings' => 1,
                    'deleted_stagings' => 0,
                    'removed_service_lines' => 2,
                    'refreshed_product_lines' => 1,
                ];
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.procurement-stagings.reconcile'), [
                'legacy_project_id' => 'cuid-1',
                'source_import_key' => 'ocn1-project:cuid-1',
            ])
            ->assertRedirect(route('erp.admin.legacy-import.projects.show', 'cuid-1'))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                'Cek staging project selesai.'
            ));
    }

    /**
     * @return array{0: ProcurementImportStaging, 1: Vendor}
     */
    private function makeStagingFixture(): array
    {
        $company = Company::query()->create([
            'name' => 'OC Networks',
            'legal_name' => 'OC Networks',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'WH-OCN',
            'name' => 'WH-OCN',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $vendor = Vendor::query()->create([
            'code' => 'SUP-000001',
            'name' => 'CV Supplier Test',
            'is_active' => true,
        ]);

        $staging = ProcurementImportStaging::query()->create([
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'source_import_key' => 'ocn1-project:test-001',
            'legacy_project_number' => 'PRJ-TEST-001',
            'legacy_project_name' => 'Project Test Legacy',
            'procurement_date' => '2026-01-07',
            'status' => 'draft',
        ]);

        $staging->lines()->create([
            'product_name' => 'Kabel UTP',
            'qty' => 1,
            'unit_cost' => 50000,
            'line_total' => 50000,
            'status' => 'draft',
        ]);

        return [$staging->fresh('lines'), $vendor];
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
