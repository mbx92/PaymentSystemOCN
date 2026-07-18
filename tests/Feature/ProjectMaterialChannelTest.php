<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Core\Models\Company;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Purchasing\Models\GoodsReceipt;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Purchasing\Models\PurchaseOrderLine;
use App\ERP\Purchasing\Models\Vendor;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class ProjectMaterialChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_add_pos_product_as_project_material(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-01',
            'name' => 'Main',
            'is_active' => true,
        ]);

        $posProduct = MasterProduct::create([
            'sku' => 'POS-00001',
            'name' => 'Barang POS',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'pos',
            'product_type' => 'finished_goods',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('projects.materials.store', $project), [
                'master_product_id' => $posProduct->id,
                'warehouse_id' => $warehouse->id,
                'planned_qty' => 1,
            ]);

        $response
            ->assertSessionHasErrors('master_product_id')
            ->assertRedirect();
    }

    public function test_can_add_project_material_product_as_project_material(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-01',
            'name' => 'Main',
            'is_active' => true,
        ]);

        $projectProduct = MasterProduct::create([
            'sku' => 'MAT-00001',
            'name' => 'Material Project',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 10,
            'reserved_qty' => 0,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('projects.materials.store', $project), [
                'master_product_id' => $projectProduct->id,
                'warehouse_id' => $warehouse->id,
                'planned_qty' => 2,
                'notes' => 'Test',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('project_materials', [
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'reserved_qty' => 2,
        ]);
    }

    public function test_direct_cctv_project_material_prices_feed_project_summary(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $project->update([
            'project_type' => 'cctv_installation',
            'total_value' => 0,
        ]);
        $warehouse = Warehouse::create([
            'code' => 'WH-01',
            'name' => 'Main',
            'is_active' => true,
        ]);
        $projectProduct = MasterProduct::create([
            'sku' => 'CAM-DIRECT-01',
            'name' => 'Kamera Direct',
            'category' => 'CCTV',
            'uom' => 'unit',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
            'selling_price' => 750000,
        ]);
        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 10,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->post(route('projects.materials.store', $project), [
                'master_product_id' => $projectProduct->id,
                'warehouse_id' => $warehouse->id,
                'planned_qty' => 2,
                'unit_cost' => 500000,
                'unit_price' => 800000,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('project_materials', [
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'unit_cost' => '500000.00',
            'unit_price' => '800000.00',
        ]);

        $this
            ->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('project.budget_summary.source', 'materials')
                ->where('project.budget_summary.total_cost', 1000000)
                ->where('project.budget_summary.total_price', 1600000)
                ->where('project.budget_summary.total_margin', 600000)
                ->where('project.materials.0.subtotal_cost', 1000000)
                ->where('project.materials.0.subtotal_price', 1600000)
                ->etc());

        $this
            ->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('projects.data.0.total_value', 1600000)
                ->etc());
    }

    public function test_can_plan_project_material_when_stock_is_empty(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-01',
            'name' => 'Main',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-00002');

        $response = $this
            ->actingAs($user)
            ->post(route('projects.materials.store', $project), [
                'master_product_id' => $projectProduct->id,
                'warehouse_id' => $warehouse->id,
                'planned_qty' => 5,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('project_materials', [
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 5,
            'reserved_qty' => 0,
            'status' => 'planned',
        ]);

        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);
    }

    public function test_material_product_search_only_returns_items_for_selected_origin_warehouse(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouseOcn = Warehouse::create([
            'code' => 'WH-OCN',
            'name' => 'Gudang OCN',
            'is_active' => true,
        ]);
        $warehouseNuma = Warehouse::create([
            'code' => 'WH-NUMA',
            'name' => 'Gudang NUMA',
            'is_active' => true,
        ]);

        $ocnProduct = MasterProduct::create([
            'sku' => 'OCN-POE-01',
            'name' => 'POE OCN',
            'category' => 'Network',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
            'warehouse_id' => $warehouseOcn->id,
        ]);
        $numaProduct = MasterProduct::create([
            'sku' => 'NUMA-POE-01',
            'name' => 'POE NUMA',
            'category' => 'Network',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
            'warehouse_id' => $warehouseNuma->id,
        ]);
        $serviceProduct = MasterProduct::create([
            'sku' => 'SRV-INSTALL-01',
            'name' => 'Jasa Instalasi',
            'category' => 'Service',
            'uom' => 'paket',
            'sales_channel' => 'project',
            'product_type' => 'service',
            'status' => 'active',
            'warehouse_id' => null,
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $ocnProduct->id,
            'warehouse_id' => $warehouseOcn->id,
            'qty' => 5,
            'reserved_qty' => 1,
        ]);
        MasterProductWarehouseStock::create([
            'master_product_id' => $numaProduct->id,
            'warehouse_id' => $warehouseNuma->id,
            'qty' => 8,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('projects.material-products.search', $project).'?warehouse_id='.$warehouseNuma->id.'&q=poe')
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $numaProduct->id);

        $this
            ->actingAs($user)
            ->getJson(route('projects.material-products.search', $project).'?warehouse_id='.$warehouseOcn->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $ocnProduct->id])
            ->assertJsonFragment(['id' => $serviceProduct->id]);
    }

    public function test_project_material_modal_uses_active_company_warehouses_only(): void
    {
        $this->disableErpMiddleware();

        $companyOcn = Company::query()->create([
            'name' => 'OCN',
            'is_active' => true,
        ]);
        $companyNuma = Company::query()->create([
            'name' => 'NUMA',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $companyOcn->id,
        ]);
        $project = $this->createProject();
        $warehouseOcn = Warehouse::create([
            'code' => 'WH-OCN',
            'company_id' => $companyOcn->id,
            'name' => 'Gudang OCN',
            'is_active' => true,
        ]);
        $warehouseNuma = Warehouse::create([
            'code' => 'WH-NUMA',
            'company_id' => $companyNuma->id,
            'name' => 'Gudang NUMA',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession([ErpCompanyResolver::SESSION_KEY => $companyOcn->id])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('warehouses', [
                    [
                        'id' => $warehouseOcn->id,
                        'code' => 'WH-OCN',
                        'name' => 'Gudang OCN',
                    ],
                ]));

        $this->actingAs($user)
            ->withSession([ErpCompanyResolver::SESSION_KEY => $companyOcn->id])
            ->getJson(route('projects.material-products.search', $project).'?warehouse_id='.$warehouseNuma->id.'&q=poe')
            ->assertStatus(422)
            ->assertJsonValidationErrors('warehouse_id');
    }

    public function test_cannot_add_stock_product_to_different_origin_warehouse(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouseOcn = Warehouse::create([
            'code' => 'WH-OCN',
            'name' => 'Gudang OCN',
            'is_active' => true,
        ]);
        $warehouseNuma = Warehouse::create([
            'code' => 'WH-NUMA',
            'name' => 'Gudang NUMA',
            'is_active' => true,
        ]);
        $projectProduct = MasterProduct::create([
            'sku' => 'MAT-ORIGIN-01',
            'name' => 'Material Warehouse Asal',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
            'warehouse_id' => $warehouseNuma->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('projects.materials.store', $project), [
                'master_product_id' => $projectProduct->id,
                'warehouse_id' => $warehouseOcn->id,
                'planned_qty' => 1,
            ])
            ->assertSessionHasErrors('master_product_id')
            ->assertRedirect();
    }

    public function test_project_service_is_recorded_without_stock_reserve_or_reorder_planning(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-01',
            'name' => 'Main',
            'is_active' => true,
        ]);
        $serviceProduct = MasterProduct::create([
            'sku' => 'SRV-00001',
            'name' => 'Jasa Instalasi',
            'category' => 'General',
            'uom' => 'paket',
            'sales_channel' => 'project',
            'product_type' => 'service',
            'status' => 'active',
            'stock' => 0,
            'min_stock' => 0,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('projects.materials.store', $project), [
                'master_product_id' => $serviceProduct->id,
                'warehouse_id' => $warehouse->id,
                'planned_qty' => 1,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('project_materials', [
            'project_id' => $project->id,
            'master_product_id' => $serviceProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 1,
            'reserved_qty' => 0,
            'status' => 'ready',
        ]);
        $this->assertDatabaseMissing('master_product_warehouse_stocks', [
            'master_product_id' => $serviceProduct->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.purchasing.reorder-planning'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Purchasing/ReorderPlanning')
                ->where('reorderSuggestions', []));
    }

    public function test_goods_receipt_allocates_project_material_shortage_to_ready(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-01',
            'name' => 'Main',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-00003');

        ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 5,
            'reserved_qty' => 0,
            'issued_qty' => 0,
            'status' => 'planned',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        Account::create(['code' => '1201', 'name' => 'Inventory', 'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]);
        Account::create(['code' => '2001', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit', 'is_active' => true]);

        $vendor = Vendor::create([
            'code' => 'SUP-001',
            'name' => 'Supplier',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-TEST-001',
            'vendor_id' => $vendor->id,
            'order_date' => now()->toDateString(),
            'total_amount' => 50000,
            'status' => DocumentStatus::Approved,
        ]);
        $purchaseOrder->lines()->create([
            'master_product_id' => $projectProduct->id,
            'qty' => 5,
            'received_qty' => 0,
            'unit_price' => 10000,
            'line_total' => 50000,
        ]);
        $receipt = GoodsReceipt::create([
            'number' => 'GR-TEST-001',
            'purchase_order_id' => $purchaseOrder->id,
            'received_date' => now()->toDateString(),
            'warehouse_id' => $warehouse->id,
            'warehouse_name' => $warehouse->name,
            'status' => DocumentStatus::Approved,
        ]);
        $receipt->lines()->create([
            'master_product_id' => $projectProduct->id,
            'qty_received' => 5,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('erp.purchasing.goods-receipts.advance', $receipt->number), [
                'action' => 'post_stock',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('project_materials', [
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'reserved_qty' => 5,
            'status' => 'ready',
        ]);

        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
            'reserved_qty' => 5,
        ]);
    }

    public function test_stock_opname_allocates_project_material_shortage_and_clears_reorder_need(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-01',
            'name' => 'Main',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-OPNAME-01');

        ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 5,
            'reserved_qty' => 0,
            'issued_qty' => 0,
            'status' => 'planned',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->post(route('erp.inventory.stock-opname.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $projectProduct->id,
                'physical_stock' => 5,
                'stock_opname_date' => now()->toDateString(),
                'note' => 'Opname masuk stok project',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('project_materials', [
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'reserved_qty' => 5,
            'status' => 'ready',
        ]);

        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => '5.00',
            'reserved_qty' => '5.00',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.purchasing.reorder-planning', [
                'warehouse_id' => $warehouse->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Purchasing/ReorderPlanning')
                ->where('reorderSuggestions', []));
    }

    public function test_finishing_project_releases_warehouse_reserved_stock(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-REL',
            'name' => 'Gudang Release',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-REL-01');

        ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 5,
            'reserved_qty' => 5,
            'issued_qty' => 0,
            'status' => 'ready',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
            'reserved_qty' => 5,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('projects.status.update', $project), [
                'target_status' => 'selesai',
                'finished_at' => '2026-05-19',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'selesai',
        ]);
        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => '5.00',
            'reserved_qty' => '0.00',
        ]);
        $this->assertDatabaseHas('project_materials', [
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'reserved_qty' => '0.00',
            'status' => 'planned',
        ]);
    }

    public function test_ready_material_can_be_checked_as_used_from_project_detail(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-USE',
            'name' => 'Gudang Pakai',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-USE-01');

        ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 5,
            'reserved_qty' => 5,
            'issued_qty' => 0,
            'status' => 'ready',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
            'reserved_qty' => 5,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('projects.materials.usage', [
                'project' => $project,
                'material' => ProjectMaterial::query()->firstOrFail(),
            ]), [
                'used' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_materials', [
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'issued_qty' => '5.00',
            'status' => 'issued',
        ]);
        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => '0.00',
            'reserved_qty' => '0.00',
        ]);
        $this->assertDatabaseHas('product_stock_movements', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => 'project_issue_out',
        ]);
    }

    public function test_used_material_can_be_unchecked_and_stock_is_returned(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-RET',
            'name' => 'Gudang Return',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-RET-01');

        $material = ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 2,
            'reserved_qty' => 2,
            'issued_qty' => 2,
            'status' => 'issued',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        ProductStockMovement::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'movement_date' => now()->toDateString(),
            'movement_type' => 'project_issue_out',
            'qty' => 2,
            'note' => 'Project issue '.$project->id.' material '.$material->id.' - '.$project->name,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('projects.materials.usage', [
                'project' => $project,
                'material' => $material,
            ]), [
                'used' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_materials', [
            'id' => $material->id,
            'issued_qty' => '0.00',
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => '2.00',
            'reserved_qty' => '2.00',
        ]);
        $this->assertDatabaseHas('product_stock_movements', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => 'project_issue_return_in',
            'qty' => '2.00',
        ]);
    }

    public function test_material_must_be_ready_before_it_can_be_checked_as_used(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-PART',
            'name' => 'Gudang Partial',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-PART-01');

        $material = ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 5,
            'reserved_qty' => 3,
            'issued_qty' => 0,
            'status' => 'partial',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
            'reserved_qty' => 3,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('projects.materials.usage', [
                'project' => $project,
                'material' => $material,
            ]), [
                'used' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('used');

        $this->assertDatabaseHas('project_materials', [
            'id' => $material->id,
            'issued_qty' => '0.00',
            'status' => 'partial',
        ]);
        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'reserved_qty' => '3.00',
        ]);
    }

    public function test_sync_project_issues_command_backfills_missing_project_issue_movements(): void
    {
        $this->disableErpMiddleware();

        $project = $this->createProject();
        $project->update([
            'status' => 'selesai',
            'finished_at' => '2026-07-05',
        ]);
        $warehouse = Warehouse::create([
            'code' => 'WH-BF',
            'name' => 'Gudang Backfill',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-BF-01');

        ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 1,
            'reserved_qty' => 1,
            'issued_qty' => 1,
            'status' => 'issued',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 1,
            'reserved_qty' => 0,
        ]);

        $this->artisan('stock:sync-project-issues', [
            'project_id' => $project->id,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => '0.00',
            'reserved_qty' => '0.00',
        ]);
        $this->assertDatabaseHas('product_stock_movements', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => 'project_issue_out',
            'qty' => '1.00',
        ]);
    }

    public function test_project_show_exposes_stock_check_payload(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-SHOW',
            'name' => 'Gudang Show',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-SHOW-01');

        ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 1,
            'reserved_qty' => 1,
            'issued_qty' => 1,
            'status' => 'issued',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 1,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('project.stock_check.summary.line_count', 1)
                ->where('project.stock_check.summary.mismatch_count', 1)
                ->where('project.stock_check.lines.0.sku', 'MAT-SHOW-01')
                ->where('project.stock_check.lines.0.is_synced', false)
                ->etc());
    }

    public function test_project_stock_sync_route_fixes_missing_issue_movements(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $project->update([
            'status' => 'selesai',
            'finished_at' => '2026-07-05',
        ]);
        $warehouse = Warehouse::create([
            'code' => 'WH-ROUTE',
            'name' => 'Gudang Route',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-ROUTE-01');

        ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 1,
            'reserved_qty' => 1,
            'issued_qty' => 1,
            'status' => 'issued',
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 1,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('projects.stock.sync', $project))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => '0.00',
        ]);
        $this->assertDatabaseHas('product_stock_movements', [
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => 'project_issue_out',
            'qty' => '1.00',
        ]);
    }

    public function test_project_material_shortage_appears_in_reorder_planning(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-01',
            'name' => 'Main',
            'is_active' => true,
        ]);
        $projectProduct = $this->createProjectProduct('MAT-00004');

        ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $projectProduct->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 4,
            'reserved_qty' => 0,
            'issued_qty' => 0,
            'status' => 'planned',
        ]);

        $vendor = Vendor::create([
            'code' => 'SUP-001',
            'name' => 'Supplier',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-TEST-002',
            'vendor_id' => $vendor->id,
            'order_date' => now()->toDateString(),
            'total_amount' => 10000,
            'status' => DocumentStatus::Approved,
        ]);
        $purchaseOrder->lines()->create([
            'master_product_id' => $projectProduct->id,
            'qty' => 1,
            'received_qty' => 0,
            'unit_price' => 10000,
            'line_total' => 10000,
        ]);
        $this->assertSame(1, ProjectMaterial::query()
            ->where('master_product_id', $projectProduct->id)
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['negosiasi', 'berjalan']))
            ->whereRaw('planned_qty > reserved_qty')
            ->count());
        $projectShortages = ProjectMaterial::query()
            ->select('master_product_id')
            ->selectRaw('SUM(CASE WHEN planned_qty > reserved_qty THEN planned_qty - reserved_qty ELSE 0 END) as shortage_qty')
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['negosiasi', 'berjalan']))
            ->whereRaw('planned_qty > reserved_qty')
            ->groupBy('master_product_id')
            ->pluck('shortage_qty', 'master_product_id');
        $onOrderQty = PurchaseOrderLine::query()
            ->select('master_product_id')
            ->selectRaw('SUM(qty - received_qty) as on_order_qty')
            ->whereRaw('qty > received_qty')
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [
                DocumentStatus::Draft->value,
                DocumentStatus::Submitted->value,
                DocumentStatus::Approved->value,
            ]))
            ->groupBy('master_product_id')
            ->pluck('on_order_qty', 'master_product_id');
        $this->assertSame(4.0, (float) ($projectShortages[$projectProduct->id] ?? 0));
        $this->assertSame(1.0, (float) ($onOrderQty[$projectProduct->id] ?? 0));

        $response = $this
            ->actingAs($user)
            ->get(route('erp.purchasing.reorder-planning'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ERP/Purchasing/ReorderPlanning')
            ->where('reorderSuggestions.0.id', $projectProduct->id)
            ->where('reorderSuggestions.0.project_shortage_qty', 4)
            ->where('reorderSuggestions.0.on_order_qty', 1)
            ->where('reorderSuggestions.0.suggested_qty', 3)
            ->etc());
    }

    public function test_finished_goods_project_shortage_appears_in_reorder_planning(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $warehouse = Warehouse::create([
            'code' => 'WH-FG',
            'name' => 'Main',
            'is_active' => true,
        ]);
        $product = MasterProduct::create([
            'sku' => 'FG-REORDER-01',
            'name' => 'Barang Jadi via Project',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => MasterProduct::PRODUCT_TYPE_FINISHED_GOODS,
            'status' => 'active',
            'stock' => 0,
            'min_stock' => 0,
            'total_sold' => 0,
        ]);

        ProjectMaterial::create([
            'project_id' => $project->id,
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 3,
            'reserved_qty' => 0,
            'issued_qty' => 0,
            'status' => 'planned',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.purchasing.reorder-planning'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Purchasing/ReorderPlanning')
                ->where('reorderSuggestions.0.id', $product->id)
                ->where('reorderSuggestions.0.project_shortage_qty', 3)
                ->where('reorderSuggestions.0.suggested_qty', 3));
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);
    }

    private function createProject(): Project
    {
        $project = new Project([
            'name' => 'P1',
            'client_name' => 'Client',
            'total_value' => 1000000,
            'status' => 'berjalan',
        ]);
        $project->id = (string) Str::uuid();
        $project->save();

        return $project;
    }

    private function createProjectProduct(string $sku): MasterProduct
    {
        return MasterProduct::create([
            'sku' => $sku,
            'name' => 'Material Project',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
            'unit_cost' => 1000,
        ]);
    }
}
