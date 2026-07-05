<?php

namespace Tests\Feature;

use App\ERP\Inventory\Models\Warehouse;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_transfer_page_filters_products_by_selected_source_warehouse(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'WH-SRC',
            'name' => 'Gudang Sumber',
            'is_active' => true,
        ]);
        $otherWarehouse = Warehouse::query()->create([
            'code' => 'WH-OTH',
            'name' => 'Gudang Lain',
            'is_active' => true,
        ]);

        $sourceProduct = $this->createStockProduct('TRF-001', 'Produk Sumber');
        $otherProduct = $this->createStockProduct('TRF-002', 'Produk Lain');
        $serviceProduct = MasterProduct::query()->create([
            'sku' => 'SRV-TRF-001',
            'name' => 'Jasa Transfer',
            'category' => 'Jasa',
            'uom' => 'paket',
            'sales_channel' => 'project',
            'product_type' => MasterProduct::PRODUCT_TYPE_SERVICE,
            'status' => 'active',
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $sourceProduct->id,
            'warehouse_id' => $sourceWarehouse->id,
            'qty' => 8,
            'reserved_qty' => 3,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $otherProduct->id,
            'warehouse_id' => $otherWarehouse->id,
            'qty' => 9,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.inventory.stock-transfer', [
                'warehouse_id' => $sourceWarehouse->id,
                'q' => 'TRF',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Inventory/StockTransfer')
                ->where('filters.warehouse_id', $sourceWarehouse->id)
                ->where('products.data.0.id', $sourceProduct->id)
                ->where('products.data.0.available_qty', 5)
                ->missing('products.data.1'));

        $this->assertDatabaseHas('master_products', [
            'id' => $serviceProduct->id,
            'product_type' => MasterProduct::PRODUCT_TYPE_SERVICE,
        ]);
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
            RoleOrPermissionMiddleware::class,
        ]);
    }

    private function createStockProduct(string $sku, string $name): MasterProduct
    {
        return MasterProduct::query()->create([
            'sku' => $sku,
            'name' => $name,
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => MasterProduct::PRODUCT_TYPE_PROJECT_MATERIAL,
            'status' => 'active',
            'stock' => 0,
            'min_stock' => 0,
        ]);
    }
}
