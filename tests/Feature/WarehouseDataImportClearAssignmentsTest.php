<?php

namespace Tests\Feature;

use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Purchasing\Models\GoodsReceipt;
use App\ERP\Purchasing\Models\GoodsReceiptLine;
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
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class WarehouseDataImportClearAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clears_zero_qty_assignments_and_recomputes_master_stock(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $wA = Warehouse::query()->create([
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'is_active' => true,
        ]);
        $wB = Warehouse::query()->create([
            'code' => 'WH-B',
            'name' => 'Gudang B',
            'is_active' => true,
        ]);

        $product = MasterProduct::query()->create([
            'sku' => 'CLR-001',
            'name' => 'Produk Clear',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'stock' => 10,
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $wA->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $wB->id,
            'qty' => 10,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('erp.admin.data-import', ['tab' => 'products']))
            ->post(route('erp.admin.data-import.warehouse-clear-products'), [
                'warehouse_id' => $wA->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('erp.admin.data-import', ['tab' => 'products']));

        $this->assertDatabaseMissing('master_product_warehouse_stocks', [
            'master_product_id' => $product->id,
            'warehouse_id' => $wA->id,
        ]);
        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $product->id,
            'warehouse_id' => $wB->id,
        ]);

        $product->refresh();
        $this->assertSame(10, $product->stock);
    }

    public function test_deletes_master_product_when_only_in_warehouse_and_no_global_relations(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $w = Warehouse::query()->create([
            'code' => 'WH-SOLO',
            'name' => 'Gudang Solo',
            'is_active' => true,
        ]);

        $product = MasterProduct::query()->create([
            'sku' => 'SOLO-001',
            'name' => 'Hanya di sini',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'stock' => 0,
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.warehouse-clear-products'), [
                'warehouse_id' => $w->id,
            ])
            ->assertRedirect();

        $this->assertSame('success', session('flash.type'));
        $this->assertDatabaseMissing('master_products', ['id' => $product->id]);
        $this->assertDatabaseMissing('master_product_warehouse_stocks', [
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
        ]);
    }

    public function test_blocked_when_exclusive_product_has_purchase_order_line(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $w = Warehouse::query()->create([
            'code' => 'WH-PO',
            'name' => 'Gudang PO',
            'is_active' => true,
        ]);

        $product = MasterProduct::query()->create([
            'sku' => 'PO-BLK',
            'name' => 'Ada PO',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'stock' => 0,
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        $vendor = Vendor::query()->create([
            'code' => 'V-PO',
            'name' => 'Vendor',
            'lead_time_days' => 1,
            'is_active' => true,
        ]);

        $po = PurchaseOrder::query()->create([
            'number' => 'PO-BLK-001',
            'vendor_id' => $vendor->id,
            'order_date' => now()->toDateString(),
            'total_amount' => 0,
            'status' => DocumentStatus::Draft,
        ]);

        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'master_product_id' => $product->id,
            'qty' => 1,
            'received_qty' => 0,
            'unit_price' => 1000,
            'line_total' => 1000,
        ]);

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.warehouse-clear-products'), [
                'warehouse_id' => $w->id,
            ])
            ->assertRedirect();

        $this->assertSame('error', session('flash.type'));
        $this->assertDatabaseHas('master_products', ['id' => $product->id]);
    }

    public function test_warns_when_warehouse_has_no_assignments(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $w = Warehouse::query()->create([
            'code' => 'WH-EMPTY',
            'name' => 'Kosong',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('erp.admin.data-import', ['tab' => 'products']))
            ->post(route('erp.admin.data-import.warehouse-clear-products'), [
                'warehouse_id' => $w->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue(session()->has('flash'));
        $this->assertSame('warning', session('flash.type'));
    }

    public function test_sync_master_product_origin_warehouses_uses_largest_qty_stock_row(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $wA = Warehouse::query()->create([
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'is_active' => true,
        ]);
        $wB = Warehouse::query()->create([
            'code' => 'WH-B',
            'name' => 'Gudang B',
            'is_active' => true,
        ]);

        $stockProduct = MasterProduct::query()->create([
            'sku' => 'SYNC-001',
            'name' => 'Produk Sync',
            'category' => 'General',
            'uom' => 'pcs',
            'warehouse_id' => null,
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'stock' => 10,
        ]);
        $serviceProduct = MasterProduct::query()->create([
            'sku' => 'SYNC-SRV',
            'name' => 'Jasa Sync',
            'category' => 'General',
            'uom' => 'pcs',
            'warehouse_id' => $wA->id,
            'sales_channel' => 'both',
            'product_type' => 'service',
            'status' => 'active',
            'stock' => 0,
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $stockProduct->id,
            'warehouse_id' => $wA->id,
            'qty' => 3,
            'reserved_qty' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $stockProduct->id,
            'warehouse_id' => $wB->id,
            'qty' => 7,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.master-products.sync-origin-warehouses'))
            ->assertRedirect(route('erp.admin.data-import', ['tab' => 'products']));

        $this->assertDatabaseHas('master_products', [
            'id' => $stockProduct->id,
            'warehouse_id' => $wB->id,
        ]);
        $this->assertDatabaseHas('master_products', [
            'id' => $serviceProduct->id,
            'warehouse_id' => null,
        ]);
    }

    public function test_clears_nonzero_qty_for_exclusive_product_and_deletes_master(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $w = Warehouse::query()->create([
            'code' => 'WH-Q',
            'name' => 'Gudang Q',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'CLR-Q',
            'name' => 'P',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'stock' => 5,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
            'qty' => 5,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.warehouse-clear-products'), [
                'warehouse_id' => $w->id,
            ])
            ->assertRedirect();

        $this->assertSame('success', session('flash.type'));
        $this->assertDatabaseMissing('master_products', ['id' => $product->id]);
    }

    public function test_blocked_when_stock_movement_exists(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $w = Warehouse::query()->create([
            'code' => 'WH-M',
            'name' => 'Gudang M',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'BLK-M',
            'name' => 'P',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'stock' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        ProductStockMovement::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
            'movement_date' => now()->toDateString(),
            'movement_type' => 'in',
            'qty' => 1,
            'note' => null,
        ]);

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.warehouse-clear-products'), [
                'warehouse_id' => $w->id,
            ])
            ->assertRedirect();

        $this->assertSame('error', session('flash.type'));
        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
        ]);
    }

    public function test_blocked_when_project_material_exists(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $w = Warehouse::query()->create([
            'code' => 'WH-PM',
            'name' => 'Gudang PM',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'BLK-PM',
            'name' => 'P',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
            'stock' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        $project = new Project([
            'name' => 'P1',
            'client_name' => 'C',
            'total_value' => 1000,
            'status' => 'berjalan',
        ]);
        $project->id = (string) Str::uuid();
        $project->save();

        ProjectMaterial::query()->create([
            'project_id' => $project->id,
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
            'planned_qty' => 1,
            'reserved_qty' => 0,
            'issued_qty' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.warehouse-clear-products'), [
                'warehouse_id' => $w->id,
            ])
            ->assertRedirect();

        $this->assertSame('error', session('flash.type'));
    }

    public function test_blocked_when_goods_receipt_line_exists_for_warehouse(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $w = Warehouse::query()->create([
            'code' => 'WH-GR',
            'name' => 'Gudang GR',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'BLK-GR',
            'name' => 'P',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'stock' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $w->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        $vendor = Vendor::query()->create([
            'code' => 'V-GR',
            'name' => 'Vendor',
            'lead_time_days' => 1,
            'is_active' => true,
        ]);

        $po = PurchaseOrder::query()->create([
            'number' => 'PO-GR-001',
            'vendor_id' => $vendor->id,
            'order_date' => now()->toDateString(),
            'total_amount' => 0,
            'status' => DocumentStatus::Draft,
        ]);

        $gr = GoodsReceipt::query()->create([
            'number' => 'GR-001',
            'purchase_order_id' => $po->id,
            'received_date' => now()->toDateString(),
            'warehouse_id' => $w->id,
            'warehouse_name' => $w->name,
            'status' => DocumentStatus::Approved,
        ]);

        GoodsReceiptLine::query()->create([
            'goods_receipt_id' => $gr->id,
            'master_product_id' => $product->id,
            'qty_received' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.warehouse-clear-products'), [
                'warehouse_id' => $w->id,
            ])
            ->assertRedirect();

        $this->assertSame('error', session('flash.type'));
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
