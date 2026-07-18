<?php

namespace Tests\Feature;

use App\ERP\CRM\Models\CrmCustomer;
use App\ERP\Inventory\Models\Warehouse;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class ProjectBudgetItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cctv_budget_total_is_calculated_from_items_with_margin(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget CCTV Gudang',
            'client_name' => 'PT Gudang',
            'project_type' => 'cctv_installation',
            'estimated_value' => 0,
            'status' => 'draft',
        ]);

        $this
            ->actingAs($user)
            ->put(route('erp.projects.budgets.update', $budget), [
                'name' => $budget->name,
                'client_name' => $budget->client_name,
                'client_contact' => null,
                'project_type' => 'cctv_installation',
                'estimated_value' => 0,
                'description' => null,
                'cctv_items' => [
                    [
                        'item_type' => 'product',
                        'name' => 'Kamera IP 4MP',
                        'uom' => 'unit',
                        'qty' => 4,
                        'unit_cost' => 500000,
                        'unit_price' => 750000,
                    ],
                    [
                        'item_type' => 'service',
                        'name' => 'Jasa instalasi',
                        'uom' => 'paket',
                        'qty' => 1,
                        'unit_cost' => 0,
                        'unit_price' => 1500000,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('project_budgets', [
            'id' => $budget->id,
            'estimated_value' => '4500000.00',
        ]);
        $this->assertDatabaseHas('project_budget_items', [
            'project_budget_id' => $budget->id,
            'name' => 'Kamera IP 4MP',
            'qty' => '4.00',
            'unit_cost' => '500000.00',
            'unit_price' => '750000.00',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.projects.budgets.show', $budget))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/BudgetShow')
                ->where('budget.estimated_value', 4500000)
                ->where('budget.total_cost', 2000000)
                ->where('budget.total_margin', 2500000)
                ->where('budget.budget_items.0.subtotal_price', 3000000)
                ->where('budget.budget_items.0.margin_amount', 1000000)
                ->etc());
    }

    public function test_budget_store_can_use_crm_customer_for_client_fields(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $customer = CrmCustomer::query()->create([
            'code' => 'CUST-BDG1',
            'name' => 'Ani Wijaya',
            'company' => 'PT Budget Client',
            'email' => 'ani@budget.test',
            'phone' => '0811111111',
            'source' => 'manual',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('erp.projects.budgets.store'), [
                'name' => 'Budget CCTV Baru',
                'crm_customer_id' => $customer->id,
                'project_type' => 'cctv_installation',
                'description' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('project_budgets', [
            'name' => 'Budget CCTV Baru',
            'crm_customer_id' => $customer->id,
            'client_name' => 'PT Budget Client',
            'client_contact' => '0811111111 / ani@budget.test',
        ]);
    }

    public function test_convert_cctv_budget_creates_project_materials_from_budget_items(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-MAIN',
            'name' => 'Gudang Utama',
            'is_active' => true,
        ]);
        $camera = MasterProduct::query()->create([
            'sku' => 'CAM-00001',
            'name' => 'IP Camera Dome',
            'category' => 'CCTV',
            'uom' => 'unit',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $camera->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 10,
            'reserved_qty' => 1,
        ]);
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget CCTV Gudang',
            'client_name' => 'PT Gudang',
            'project_type' => 'cctv_installation',
            'estimated_value' => 3000000,
            'status' => 'deal',
            'deal_at' => now(),
        ]);
        $budget->items()->create([
            'master_product_id' => $camera->id,
            'item_type' => 'material',
            'name' => 'IP Camera Dome',
            'uom' => 'unit',
            'qty' => 4,
            'unit_cost' => 500000,
            'unit_price' => 750000,
            'sort_order' => 1,
        ]);

        $this
            ->actingAs($user)
            ->post(route('erp.projects.budgets.convert', $budget))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $budget->refresh();

        $this->assertNotNull($budget->converted_project_id);
        $this->assertDatabaseHas('projects', [
            'id' => $budget->converted_project_id,
            'status' => 'berjalan',
        ]);
        $this->assertDatabaseHas('project_materials', [
            'project_id' => $budget->converted_project_id,
            'master_product_id' => $camera->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => '4.00',
            'reserved_qty' => '4.00',
            'status' => 'ready',
        ]);
        $this->assertSame(4.0, (float) ProjectMaterial::query()
            ->where('project_id', $budget->converted_project_id)
            ->where('master_product_id', $camera->id)
            ->value('planned_qty'));
        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $camera->id,
            'warehouse_id' => $warehouse->id,
            'reserved_qty' => '4.00',
        ]);
    }

    public function test_convert_creates_project_material_for_each_budget_item_including_services(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-MAIN',
            'name' => 'Gudang Utama',
            'is_active' => true,
        ]);
        $camera = MasterProduct::query()->create([
            'sku' => 'CAM-00002',
            'name' => 'IP Camera Bullet',
            'category' => 'CCTV',
            'uom' => 'unit',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $camera->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
            'reserved_qty' => 0,
        ]);
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget CCTV Lengkap',
            'client_name' => 'PT ABC',
            'project_type' => 'cctv_installation',
            'estimated_value' => 5000000,
            'status' => 'deal',
            'deal_at' => now(),
        ]);
        $budget->items()->createMany([
            [
                'master_product_id' => $camera->id,
                'item_type' => 'material',
                'name' => 'IP Camera Bullet',
                'uom' => 'unit',
                'qty' => 2,
                'unit_cost' => 400000,
                'unit_price' => 600000,
                'sort_order' => 1,
            ],
            [
                'item_type' => 'service',
                'name' => 'Jasa instalasi',
                'uom' => 'paket',
                'qty' => 1,
                'unit_cost' => 0,
                'unit_price' => 1500000,
                'sort_order' => 2,
            ],
            [
                'item_type' => 'material',
                'name' => 'Kabel LAN 100m',
                'uom' => 'roll',
                'qty' => 3,
                'unit_cost' => 250000,
                'unit_price' => 350000,
                'sort_order' => 3,
            ],
        ]);

        $this
            ->actingAs($user)
            ->post(route('erp.projects.budgets.convert', $budget))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $budget->refresh();

        $this->assertSame(3, ProjectMaterial::query()->where('project_id', $budget->converted_project_id)->count());
        $this->assertSame('berjalan', Project::query()->find($budget->converted_project_id)?->status);
    }

    public function test_budget_builder_page_loads_for_itemized_budget(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget RAB Builder',
            'client_name' => 'PT Builder',
            'project_type' => 'cctv_installation',
            'estimated_value' => 0,
            'status' => 'draft',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.projects.budgets.builder', $budget))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/BudgetBuilder')
                ->where('budget.id', $budget->id)
                ->where('can_edit', true)
                ->has('cctv_products')
                ->has('catalog_sheets')
                ->etc());
    }

    public function test_budget_builder_redirects_for_non_itemized_project_type(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget Software',
            'client_name' => 'PT Software',
            'project_type' => 'software_development',
            'estimated_value' => 10000000,
            'status' => 'draft',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.projects.budgets.builder', $budget))
            ->assertRedirect(route('erp.projects.budgets.show', $budget));
    }

    public function test_budget_can_be_cancelled_and_clears_deal_date(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget Batal',
            'client_name' => 'PT Batal',
            'project_type' => 'cctv_installation',
            'estimated_value' => 2500000,
            'status' => 'deal',
            'deal_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->patch(route('erp.projects.budgets.cancel', $budget))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $budget->refresh();

        $this->assertSame('cancelled', $budget->status);
        $this->assertNull($budget->deal_at);
    }

    public function test_cancelled_budget_cannot_be_updated(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget Terkunci',
            'client_name' => 'PT Locked',
            'project_type' => 'cctv_installation',
            'estimated_value' => 0,
            'status' => 'cancelled',
        ]);

        $this
            ->actingAs($user)
            ->put(route('erp.projects.budgets.update', $budget), [
                'name' => 'Budget Terkunci Edit',
                'client_name' => 'PT Locked',
                'client_contact' => null,
                'project_type' => 'cctv_installation',
                'estimated_value' => 0,
                'description' => null,
                'cctv_items' => [],
            ])
            ->assertSessionHasErrors('budget');
    }

    public function test_budget_customer_view_applies_markup_only_to_catalog_items(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget Customer View',
            'client_name' => 'PT Customer',
            'project_type' => 'cctv_installation',
            'estimated_value' => 380000,
            'status' => 'draft',
        ]);

        $budget->items()->create([
            'catalog_sheet' => 'tiandy',
            'catalog_ref' => 'IPCTIA001',
            'item_type' => 'material',
            'name' => 'IP Camera Katalog',
            'uom' => 'unit',
            'qty' => 2,
            'unit_cost' => 100000,
            'unit_price' => 100000,
            'sort_order' => 1,
        ]);

        $masterProduct = MasterProduct::query()->create([
            'sku' => 'CAM-MASTER-01',
            'name' => 'IP Camera Master Product',
            'category' => 'CCTV',
            'uom' => 'unit',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
            'selling_price' => 150000,
            'unit_cost' => 80000,
        ]);

        $budget->items()->create([
            'master_product_id' => $masterProduct->id,
            'item_type' => 'material',
            'name' => 'IP Camera Master',
            'uom' => 'unit',
            'qty' => 1,
            'unit_cost' => 80000,
            'unit_price' => 150000,
            'sort_order' => 2,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.projects.budgets.customer-view', $budget))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/BudgetCustomerView')
                ->where('markup_percent', 40)
                ->where('items.0.unit_price', 140000)
                ->where('items.0.subtotal', 280000)
                ->where('items.0.from_catalog', true)
                ->where('items.1.unit_price', 150000)
                ->where('items.1.subtotal', 150000)
                ->where('items.1.from_catalog', false)
                ->where('total', 430000)
                ->etc());
    }

    public function test_budget_customer_view_redirects_for_non_itemized_project_type(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget Software Customer',
            'client_name' => 'PT Software',
            'project_type' => 'software_development',
            'estimated_value' => 10000000,
            'status' => 'draft',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.projects.budgets.customer-view', $budget))
            ->assertRedirect(route('erp.projects.budgets.show', $budget));
    }

    public function test_signed_budget_customer_view_is_accessible_without_login(): void
    {
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget Signed Share',
            'client_name' => 'PT Signed',
            'project_type' => 'cctv_installation',
            'estimated_value' => 1000000,
            'status' => 'draft',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'erp.projects.budgets.customer-view.signed',
            now()->addMinutes(30),
            ['budget' => $budget->getKey()]
        );

        $this
            ->get($signedUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/BudgetCustomerView')
                ->where('budget.id', $budget->id)
                ->where('budget.client_name', 'PT Signed')
                ->etc());
    }

    public function test_signed_budget_pdf_is_accessible_without_login(): void
    {
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget Signed PDF',
            'client_name' => 'PT Signed PDF',
            'project_type' => 'cctv_installation',
            'estimated_value' => 1200000,
            'status' => 'draft',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'erp.projects.budgets.customer-pdf.signed',
            now()->addMinutes(30),
            ['budget' => $budget->getKey()]
        );

        $this
            ->get($signedUrl)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
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
