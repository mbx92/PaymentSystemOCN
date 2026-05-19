<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
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
        ]);
    }
}
