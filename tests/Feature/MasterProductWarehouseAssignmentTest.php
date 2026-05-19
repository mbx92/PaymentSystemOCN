<?php

namespace Tests\Feature;

use App\ERP\Inventory\Models\Warehouse;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class MasterProductWarehouseAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_product_can_store_operational_warehouse_without_stock(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-SVC',
            'name' => 'Gudang Service',
            'is_active' => true,
        ]);

        DB::table('product_categories')->insert([
            'name' => 'Jasa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('uoms')->insert([
            'code' => 'pekerjaan',
            'name' => 'Pekerjaan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('erp.master-products.store'), [
                'sku' => 'SVC-WH-0001',
                'name' => 'Maintenance Visit',
                'category' => 'Jasa',
                'uom' => 'pekerjaan',
                'warehouse_id' => $warehouse->id,
                'sales_channel' => 'project',
                'product_type' => MasterProduct::PRODUCT_TYPE_SERVICE,
                'status' => 'active',
                'selling_price' => 150000,
                'stock' => 7,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('master_products', [
            'sku' => 'SVC-WH-0001',
            'warehouse_id' => $warehouse->id,
            'stock' => 0,
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
