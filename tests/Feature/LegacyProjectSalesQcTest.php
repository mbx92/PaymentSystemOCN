<?php

namespace Tests\Feature;

use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\User;
use App\Services\LegacyProjectSalesQcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class LegacyProjectSalesQcTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_run_legacy_project_sales_qc_and_receive_report(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->instance(LegacyProjectSalesQcService::class, new class extends LegacyProjectSalesQcService
        {
            public function buildReport(): array
            {
                return [
                    'generated_at' => '2026-05-26 10:00:00',
                    'source' => [
                        'connection' => 'legacy_ocn',
                        'host' => '10.50.30.46',
                        'database' => 'ocn_db',
                        'schema' => 'public',
                    ],
                    'scope' => [
                        'included' => ['Project', 'Customer', 'Payment'],
                        'ignored' => ['PurchaseOrder'],
                        'date_rules' => [
                            'project_sale_date' => 'startDate jika ada, fallback ke createdAt',
                            'payment_real_date' => 'paymentDate',
                        ],
                    ],
                    'summary' => [
                        'total_projects' => 74,
                        'total_payments' => 72,
                        'ready_projects' => 60,
                        'warning_projects' => 10,
                        'blocked_projects' => 4,
                        'projects_without_payments' => 4,
                        'payment_mismatch_projects' => 3,
                        'warning_issues' => 12,
                        'error_issues' => 4,
                        'legacy_project_total' => 123456789.00,
                        'legacy_paid_total' => 120000000.00,
                    ],
                    'issues' => [
                        [
                            'severity' => 'warning',
                            'project_number' => 'PRJ-202501-001',
                            'title' => 'Penggantian AP',
                            'message' => 'Data customer belum lengkap (phone/alamat kosong).',
                        ],
                    ],
                    'projects' => [
                        [
                            'legacy_id' => 'cuid-1',
                            'import_key' => 'ocn1-project:cuid-1',
                            'project_number' => 'PRJ-202501-001',
                            'title' => 'Penggantian AP',
                            'customer_name' => '54DM Cafe',
                            'status' => 'COMPLETED',
                            'sale_date' => '2025-01-04',
                            'sale_date_source' => 'createdAt',
                            'expected_value' => 2250000,
                            'paid_total' => 2250000,
                            'payment_count' => 1,
                            'readiness' => 'warning',
                            'issues_count' => 1,
                            'last_payment_date' => '2025-01-04',
                        ],
                    ],
                ];
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.legacy-sales.qc'))
            ->assertRedirect(route('erp.admin.legacy-import'))
            ->assertSessionHas('legacy_project_sales_qc', fn (array $report) => ($report['summary']['total_projects'] ?? null) === 74)
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                'QC legacy selesai.'
            ));
    }

    public function test_admin_can_open_legacy_import_workspace(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('erp.admin.legacy-import'))
            ->assertOk();
    }

    public function test_admin_can_open_legacy_import_project_detail_workspace(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->instance(LegacyProjectSalesQcService::class, new class extends LegacyProjectSalesQcService
        {
            public function buildReport(): array
            {
                return [
                    'generated_at' => '2026-05-26 10:00:00',
                    'source' => [
                        'database' => 'ocn_db',
                    ],
                    'projects' => [
                        [
                            'legacy_id' => 'cuid-1',
                            'import_key' => 'ocn1-project:cuid-1',
                            'project_number' => 'PRJ-202501-001',
                            'title' => 'Penggantian AP',
                            'customer_name' => '54DM Cafe',
                            'crm_customer_match' => null,
                            'status' => 'COMPLETED',
                            'existing_erp_project' => null,
                            'import_status' => [
                                'key' => 'pending_import',
                                'label' => 'Belum diimport',
                                'description' => 'Project legacy belum dibuat di ERP.',
                                'badge' => 'badge-ghost',
                            ],
                            'is_importable' => true,
                            'sale_date' => '2025-01-04',
                            'sale_date_source' => 'createdAt',
                            'expected_value_source' => 'finalPrice',
                            'expected_value' => 2250000,
                            'paid_total' => 2250000,
                            'payment_count' => 1,
                            'readiness' => 'warning',
                            'issues_count' => 1,
                            'last_payment_date' => '2025-01-04',
                            'issues' => [],
                            'compare_summary' => [
                                'items_total' => 1,
                                'items_unresolved' => 0,
                                'technicians_total' => 1,
                                'technicians_unresolved' => 0,
                                'technician_payments_total' => 1,
                                'technician_payments_unresolved' => 0,
                            ],
                            'details' => [
                                'items' => [],
                                'technicians' => [],
                                'technician_payments' => [],
                            ],
                        ],
                    ],
                ];
            }
        });

        $this->actingAs($user)
            ->get(route('erp.admin.legacy-import.projects.show', 'cuid-1'))
            ->assertOk();
    }

    public function test_legacy_project_sales_qc_failure_returns_error_flash(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this->instance(LegacyProjectSalesQcService::class, new class extends LegacyProjectSalesQcService
        {
            public function buildReport(): array
            {
                throw new RuntimeException('legacy connection timeout');
            }
        });

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.legacy-sales.qc'))
            ->assertRedirect(route('erp.admin.legacy-import'))
            ->assertSessionHas('flash', fn (array $flash) => str_contains(
                $flash['message'] ?? '',
                'legacy connection timeout'
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
