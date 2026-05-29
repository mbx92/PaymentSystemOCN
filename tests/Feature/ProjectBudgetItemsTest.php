<?php

namespace Tests\Feature;

use App\ERP\Inventory\Models\Warehouse;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProjectBudget;
use App\Models\ProjectMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'reserved_qty' => '5.00',
        ]);
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
