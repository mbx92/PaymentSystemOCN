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

    public function test_stock_management_search_can_find_items_outside_current_warehouse_listing_subset(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-01',
            'name' => 'Gudang Utama',
            'is_active' => true,
        ]);
        $listed = $this->createProduct('LIST-001', 'Produk Tampil Default', 2);
        $searchOnly = $this->createProduct('FIND-999', 'Produk Hanya Search', 2);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $listed->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 9,
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
                ->where('products.data.0.id', $listed->id)
                ->missing('products.data.1'));

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-management', [
                'warehouse_id' => $warehouse->id,
                'q' => 'FIND-999',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockManagement')
                ->where('products.data.0.id', $searchOnly->id)
                ->where('products.data.0.stock', 0)
                ->where('filters.q', 'FIND-999')
                ->missing('products.data.1'));
    }

    public function test_stock_management_search_is_case_insensitive_for_postgresql(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-OCN',
            'name' => 'Gudang WH-OCN',
            'is_active' => true,
        ]);
        $product = $this->createProduct('NET-POE-0001', 'Switch POE Hilook 5 Port', 0);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-management', [
                'warehouse_id' => $warehouse->id,
                'q' => 'poe',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockManagement')
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.sku', 'NET-POE-0001')
                ->where('filters.q', 'poe'));
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
        $target->update(['warehouse_id' => $warehouse->id]);
        $other->update(['warehouse_id' => $warehouse->id]);

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

    public function test_stock_opname_only_lists_items_that_exist_in_selected_warehouse(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-01',
            'name' => 'Gudang Utama',
            'is_active' => true,
        ]);
        $available = $this->createProduct('POE-001', 'Switch POE Warehouse', 2);
        $missing = $this->createProduct('POE-002', 'Switch POE Belum Ada', 2);
        $zeroQty = $this->createProduct('POE-003', 'Switch POE Qty Nol', 2);
        $otherOrigin = $this->createProduct('POE-004', 'Switch POE Warehouse Lain', 2);

        $available->update(['warehouse_id' => $warehouse->id]);
        $zeroQty->update(['warehouse_id' => $warehouse->id]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $available->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 3,
            'reserved_qty' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $zeroQty->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $otherOrigin->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 4,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-opname', [
                'warehouse_id' => $warehouse->id,
                'q' => 'POE',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockOpname')
                ->where('products.0.id', $zeroQty->id)
                ->where('products.0.sku', 'POE-003')
                ->where('products.1.id', $available->id)
                ->where('products.1.sku', 'POE-001')
                ->missing('products.2'));
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

    public function test_stock_management_marks_products_with_movement_qty_mismatch(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-MM',
            'name' => 'Gudang Mismatch',
            'is_active' => true,
        ]);
        $product = $this->createProduct('MM-001', 'Produk Mismatch', 2);
        $product->update([
            'description' => 'Rak belakang dekat kabel cadangan',
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);
        ProductStockMovement::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_date' => '2026-05-19',
            'movement_type' => 'opname_in',
            'qty' => 5,
            'note' => 'Mismatch test',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-management', [
                'warehouse_id' => $warehouse->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockManagement')
                ->where('stock_movement_mismatch.count', 1)
                ->where('products.data.0.movement_mismatch', true)
                ->where('products.data.0.movement_expected_qty', 5)
                ->where('products.data.0.movement_delta_qty', 5)
                ->where('products.data.0.description', 'Rak belakang dekat kabel cadangan')
                ->where('products.data.0.recent_movements.0.type', 'opname_in')
                ->where('products.data.0.recent_movements.0.note', 'Mismatch test')
                ->etc());
    }

    private function createProduct(string $sku, string $name, int $minStock, string $status = 'active'): MasterProduct
    {
        return MasterProduct::query()->create([
            'sku' => $sku,
            'name' => $name,
            'category' => 'Material',
            'uom' => 'pcs',
            'warehouse_id' => null,
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
