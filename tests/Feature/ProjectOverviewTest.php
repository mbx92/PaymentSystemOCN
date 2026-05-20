<?php

namespace Tests\Feature;

use App\ERP\CRM\Models\CrmCustomer;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\ProjectTask;
use App\Models\User;
use App\ERP\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_overview_renders_workspace_statistics(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $customer = CrmCustomer::query()->create([
            'code' => 'CUST-OVR-01',
            'name' => 'Overview Customer',
            'company' => 'PT Overview',
            'source' => 'manual',
            'is_active' => true,
        ]);

        $project = Project::query()->create([
            'name' => 'Dashboard Statistik Project',
            'crm_customer_id' => $customer->id,
            'client_name' => 'PT Overview',
            'status' => 'berjalan',
            'project_type' => 'system_website_development',
            'total_value' => 12000000,
        ]);
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-OVR',
            'name' => 'Gudang Overview',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'PRJ-00001',
            'name' => 'Router Dashboard',
            'category' => 'Networking',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => MasterProduct::PRODUCT_TYPE_PROJECT_MATERIAL,
            'status' => 'active',
            'selling_price' => 150000,
            'stock' => 0,
        ]);

        CashIn::query()->create([
            'project_id' => $project->id,
            'category' => 'project_payment',
            'amount' => 5000000,
            'date' => now()->startOfYear()->addMonth(),
            'created_by' => $user->id,
        ]);

        CashOut::query()->create([
            'project_id' => $project->id,
            'category' => 'operasional',
            'amount' => 1250000,
            'date' => now()->startOfYear()->addMonth(),
            'recipient_name' => 'Vendor Implementasi',
            'created_by' => $user->id,
        ]);

        ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Setup dashboard',
            'status' => 'done',
            'sort_order' => 1,
        ]);

        ProjectMaterial::query()->create([
            'project_id' => $project->id,
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 10,
            'reserved_qty' => 8,
            'issued_qty' => 4,
            'unit_cost' => 100000,
            'unit_price' => 150000,
            'status' => 'partial',
        ]);

        $this->actingAs($user)
            ->get(route('projects.overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Overview')
                ->where('stats.project_count', 1)
                ->where('stats.total_contract_value', 12000000)
                ->where('stats.total_collected', 5000000)
                ->where('status_summary.berjalan', 1)
                ->where('task_summary.done', 1)
                ->where('material_summary.lines', 1)
                ->has('recent_projects', 1)
                ->has('monthly_data', 12)
            );
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ErpMaintenanceMode::class,
            \App\Http\Middleware\LogErpActivity::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }
}
