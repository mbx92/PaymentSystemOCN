<?php

namespace Tests\Feature;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockManagementFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_management_can_filter_low_stock_products_by_selected_warehouse(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-01',
            'name' => 'Gudang Utama',
            'is_active' => true,
        ]);
        $lowStockProduct = $this->createProduct('LOW-001', 'Kabel Low', 5);
        $safeStockProduct = $this->createProduct('SAFE-001', 'Kabel Aman', 5);
        $serviceProduct = MasterProduct::query()->create([
            'sku' => 'SRV-001',
            'name' => 'Jasa Instalasi',
            'category' => 'Jasa',
            'uom' => 'paket',
            'sales_channel' => 'project',
            'product_type' => MasterProduct::PRODUCT_TYPE_SERVICE,
            'status' => 'active',
            'stock' => 0,
            'min_stock' => 0,
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $lowStockProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 4,
            'reserved_qty' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $safeStockProduct->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 12,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-management', [
                'warehouse_id' => $warehouse->id,
                'low_stock_only' => 1,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockManagement')
                ->where('products.data.0.id', $lowStockProduct->id)
                ->where('products.data.0.available_qty', 4)
                ->where('filters.low_stock_only', true)
                ->missing('products.data.1'));

        $this->assertDatabaseHas('master_products', [
            'id' => $serviceProduct->id,
            'product_type' => MasterProduct::PRODUCT_TYPE_SERVICE,
        ]);
    }

    public function test_stock_management_search_and_status_filters_are_applied(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-01',
            'name' => 'Gudang Utama',
            'is_active' => true,
        ]);
        $target = $this->createProduct('MAT-ABC', 'Kabel ABC', 2);
        $inactive = $this->createProduct('MAT-XYZ', 'Kabel XYZ', 2, 'inactive');
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $target->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 9,
            'reserved_qty' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $inactive->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 4,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-management', [
                'warehouse_id' => $warehouse->id,
                'q' => 'ABC',
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockManagement')
                ->where('products.data.0.id', $target->id)
                ->where('filters.q', 'ABC')
                ->where('filters.status', 'active')
                ->missing('products.data.1'));

        $this->assertDatabaseHas('master_products', [
            'id' => $inactive->id,
            'status' => 'inactive',
        ]);
    }

    public function test_low_stock_notification_can_be_toggled_per_product(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $product = $this->createProduct('LOW-NOTIF-001', 'Kabel Notif', 5);

        $this
            ->actingAs($user)
            ->put(route('erp.inventory.stock-management.update', $product), [
                'min_stock' => 7,
                'low_stock_alert_enabled' => false,
                'note' => 'Matikan alert',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_products', [
            'id' => $product->id,
            'min_stock' => 7,
            'low_stock_alert_enabled' => false,
        ]);
    }

    public function test_low_stock_notifications_can_be_batch_toggled_for_all_stock_products(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $stockProduct = $this->createProduct('BATCH-001', 'Produk Batch', 5);
        $serviceProduct = MasterProduct::query()->create([
            'sku' => 'SRV-BATCH-001',
            'name' => 'Jasa Batch',
            'category' => 'Jasa',
            'uom' => 'paket',
            'sales_channel' => 'project',
            'product_type' => MasterProduct::PRODUCT_TYPE_SERVICE,
            'status' => 'active',
            'stock' => 0,
            'min_stock' => 0,
            'low_stock_alert_enabled' => false,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('erp.inventory.stock-management.low-stock-alerts.batch'), [
                'enabled' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_products', [
            'id' => $stockProduct->id,
            'low_stock_alert_enabled' => false,
        ]);
        $this->assertDatabaseHas('master_products', [
            'id' => $serviceProduct->id,
            'low_stock_alert_enabled' => false,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('erp.inventory.stock-management.low-stock-alerts.batch'), [
                'enabled' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_products', [
            'id' => $stockProduct->id,
            'low_stock_alert_enabled' => true,
        ]);
        $this->assertDatabaseHas('master_products', [
            'id' => $serviceProduct->id,
            'low_stock_alert_enabled' => false,
        ]);
    }

    public function test_disabled_low_stock_notifications_are_excluded_from_global_alerts(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-01',
            'name' => 'Gudang Utama',
            'is_active' => true,
        ]);
        $product = $this->createProduct('LOW-DISABLED-001', 'Low Disabled', 5);
        $product->update([
            'stock' => 1,
            'low_stock_alert_enabled' => false,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 1,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-management', [
                'warehouse_id' => $warehouse->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockManagement')
                ->where('inventoryAlerts.lowStockCount', 0)
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.low_stock_alert_enabled', false));
    }

    public function test_stock_management_only_shows_items_from_selected_warehouse(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouseA = Warehouse::query()->create([
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'is_active' => true,
        ]);
        $warehouseB = Warehouse::query()->create([
            'code' => 'WH-B',
            'name' => 'Gudang B',
            'is_active' => true,
        ]);
        $productA = $this->createProduct('WHA-001', 'Produk Gudang A', 1);
        $productB = $this->createProduct('WHB-001', 'Produk Gudang B', 1);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $productA->id,
            'warehouse_id' => $warehouseA->id,
            'qty' => 3,
            'reserved_qty' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $productB->id,
            'warehouse_id' => $warehouseB->id,
            'qty' => 7,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-management', [
                'warehouse_id' => $warehouseA->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockManagement')
                ->where('products.data.0.id', $productA->id)
                ->missing('products.data.1'));
    }

    public function test_stock_opname_page_can_filter_products_by_warehouse_and_search(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-01',
            'name' => 'Gudang Utama',
            'is_active' => true,
        ]);
        $target = $this->createProduct('OPN-ABC', 'Kabel Opname ABC', 2);
        $other = $this->createProduct('OPN-XYZ', 'Kabel Opname XYZ', 2);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $target->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 8,
            'reserved_qty' => 1,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-opname', [
                'warehouse_id' => $warehouse->id,
                'q' => 'ABC',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockOpname')
                ->where('filters.warehouse_id', $warehouse->id)
                ->where('filters.q', 'ABC')
                ->where('products.0.id', $target->id)
                ->where('products.0.warehouse_stock', 8)
                ->where('products.0.reserved_qty', 1)
                ->missing('products.1'));

        $this->assertDatabaseHas('master_products', [
            'id' => $other->id,
            'sku' => 'OPN-XYZ',
        ]);
    }

    public function test_stock_opname_updates_selected_warehouse_stock_and_movement_date(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouseA = Warehouse::query()->create([
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'is_active' => true,
        ]);
        $warehouseB = Warehouse::query()->create([
            'code' => 'WH-B',
            'name' => 'Gudang B',
            'is_active' => true,
        ]);
        $product = $this->createProduct('OPN-001', 'Produk Opname', 2);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouseA->id,
            'qty' => 5,
            'reserved_qty' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouseB->id,
            'qty' => 7,
            'reserved_qty' => 0,
        ]);
        $product->update(['stock' => 12]);

        $this
            ->actingAs($user)
            ->post(route('erp.inventory.stock-opname.store'), [
                'warehouse_id' => $warehouseA->id,
                'product_id' => $product->id,
                'physical_stock' => 9,
                'stock_opname_date' => '2026-05-19',
                'note' => 'Hitung ulang rak A',
            ])
            ->assertRedirect(route('erp.inventory.stock-opname', ['warehouse_id' => $warehouseA->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouseA->id,
            'qty' => '9.00',
        ]);
        $this->assertDatabaseHas('master_products', [
            'id' => $product->id,
            'stock' => 16,
        ]);
        $this->assertDatabaseHas('product_stock_movements', [
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouseA->id,
            'movement_date' => '2026-05-19 00:00:00',
            'movement_type' => 'opname_in',
            'qty' => 4,
            'note' => 'Hitung ulang rak A',
        ]);

        $this->assertSame(1, ProductStockMovement::query()->count());
    }

    private function createProduct(string $sku, string $name, int $minStock, string $status = 'active'): MasterProduct
    {
        return MasterProduct::query()->create([
            'sku' => $sku,
            'name' => $name,
            'category' => 'Material',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => MasterProduct::PRODUCT_TYPE_PROJECT_MATERIAL,
            'status' => $status,
            'stock' => 0,
            'min_stock' => $minStock,
            'total_sold' => 0,
            'selling_price' => 10000,
        ]);
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
