<?php

namespace Tests\Feature;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RebuildWarehouseStockFromMovementsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_mismatch_without_changing_stock(): void
    {
        [$product, $warehouse] = $this->seedMismatchStock();

        $this->artisan('stock:rebuild-from-movements')
            ->assertSuccessful()
            ->expectsOutputToContain('DRY-RUN');

        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => '9.00',
        ]);
    }

    public function test_apply_rebuilds_qty_from_movements_including_gr_reopen(): void
    {
        [$product, $warehouse] = $this->seedMismatchStock();

        $this->artisan('stock:rebuild-from-movements', ['--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Rebuild selesai');

        // GR in 10 - reopen out 3 = 7
        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => '7.00',
        ]);
        $this->assertDatabaseHas('master_products', [
            'id' => $product->id,
            'stock' => 7,
        ]);
    }

    /**
     * @return array{0: MasterProduct, 1: Warehouse}
     */
    private function seedMismatchStock(): array
    {
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-RBLD',
            'name' => 'Gudang Rebuild',
            'is_active' => true,
        ]);

        $product = MasterProduct::query()->create([
            'sku' => 'SKU-RBLD-01',
            'name' => 'Produk Rebuild',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => MasterProduct::PRODUCT_TYPE_FINISHED_GOODS,
            'status' => 'active',
            'stock' => 9,
            'min_stock' => 0,
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 9,
            'reserved_qty' => 0,
        ]);

        ProductStockMovement::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_date' => now()->toDateString(),
            'movement_type' => 'purchase_receipt',
            'qty' => 10,
            'note' => 'GR post',
        ]);

        ProductStockMovement::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_date' => now()->toDateString(),
            'movement_type' => 'purchase_reopen_out',
            'qty' => 3,
            'note' => 'GR reopen',
        ]);

        return [$product, $warehouse];
    }
}
