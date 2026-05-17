<?php

namespace Tests\Feature;

use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Purchasing\Models\Vendor;
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

class PurchasingReorderPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_detail_exposes_active_suppliers_for_direct_po_creation(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $product = $this->createProduct();
        Vendor::query()->create([
            'code' => 'SUP-001',
            'name' => 'Supplier Aktif',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);
        Vendor::query()->create([
            'code' => 'SUP-VOID',
            'name' => 'Supplier Nonaktif',
            'lead_time_days' => 7,
            'is_active' => false,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.purchasing.reorder-planning.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Purchasing/ReorderShow')
                ->where('detail.id', $product->id)
                ->where('detail.selling_price', 25000)
                ->where('suppliers.0.code', 'SUP-001')
                ->missing('suppliers.1')
                ->etc());
    }

    public function test_purchase_order_store_redirects_to_created_po_detail(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $product = $this->createProduct();
        $vendor = Vendor::query()->create([
            'code' => 'SUP-001',
            'name' => 'Supplier Aktif',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('erp.purchasing.purchase-orders.store'), [
                'vendor_code' => $vendor->code,
                'order_date' => '2026-05-13',
                'eta_date' => '2026-05-20',
                'notes' => 'Generated from reorder planning',
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'qty' => 4,
                        'unit_price' => 25000,
                    ],
                ],
            ]);

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('erp.purchasing.purchase-orders.show', $purchaseOrder));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'vendor_id' => $vendor->id,
            'total_amount' => '100000.00',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('purchase_order_lines', [
            'purchase_order_id' => $purchaseOrder->id,
            'master_product_id' => $product->id,
            'qty' => '4.00',
            'unit_price' => '25000.00',
            'line_total' => '100000.00',
        ]);
    }

    public function test_reorder_planning_can_filter_suggestions_by_warehouse(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $mainWarehouse = Warehouse::query()->create([
            'code' => 'WH-MAIN',
            'name' => 'Main Warehouse',
            'is_active' => true,
        ]);
        $otherWarehouse = Warehouse::query()->create([
            'code' => 'WH-OTH',
            'name' => 'Other Warehouse',
            'is_active' => true,
        ]);
        $mainProduct = $this->createProduct([
            'sku' => 'MAT-WH-001',
            'name' => 'Material Main Warehouse',
            'stock' => 99,
            'min_stock' => 10,
        ]);
        $otherProduct = $this->createProduct([
            'sku' => 'MAT-WH-002',
            'name' => 'Material Other Warehouse',
            'stock' => 99,
            'min_stock' => 10,
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $mainProduct->id,
            'warehouse_id' => $mainWarehouse->id,
            'qty' => 2,
            'reserved_qty' => 0,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $otherProduct->id,
            'warehouse_id' => $otherWarehouse->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        $project = new Project([
            'name' => 'P1',
            'client_name' => 'Client',
            'total_value' => 1000000,
            'status' => 'berjalan',
        ]);
        $project->id = (string) Str::uuid();
        $project->save();
        ProjectMaterial::query()->create([
            'project_id' => $project->id,
            'master_product_id' => $mainProduct->id,
            'warehouse_id' => $mainWarehouse->id,
            'planned_qty' => 5,
            'reserved_qty' => 3,
            'issued_qty' => 0,
            'status' => 'planned',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.purchasing.reorder-planning', ['warehouse_id' => $mainWarehouse->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Purchasing/ReorderPlanning')
                ->where('filters.warehouse_id', (string) $mainWarehouse->id)
                ->where('warehouses.0.code', 'WH-MAIN')
                ->where('reorderSuggestions.0.id', $mainProduct->id)
                ->where('reorderSuggestions.0.stock', 2)
                ->where('reorderSuggestions.0.project_shortage_qty', 2)
                ->where('reorderSuggestions.0.suggested_qty', 10)
                ->missing('reorderSuggestions.1')
                ->etc());
    }

    public function test_service_product_cannot_be_added_to_purchase_order(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $service = MasterProduct::query()->create([
            'sku' => 'SRV-PO-001',
            'name' => 'Jasa Instalasi',
            'category' => 'Jasa',
            'uom' => 'paket',
            'sales_channel' => 'project',
            'product_type' => 'service',
            'status' => 'active',
            'stock' => 0,
            'min_stock' => 0,
            'total_sold' => 0,
            'selling_price' => 250000,
        ]);
        $vendor = Vendor::query()->create([
            'code' => 'SUP-001',
            'name' => 'Supplier Aktif',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('erp.purchasing.purchase-orders.store'), [
                'vendor_code' => $vendor->code,
                'order_date' => '2026-05-13',
                'eta_date' => '2026-05-20',
                'lines' => [
                    [
                        'product_id' => $service->id,
                        'qty' => 1,
                        'unit_price' => 250000,
                    ],
                ],
            ])
            ->assertSessionHasErrors('lines.0.product_id');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_reorder_planning_uses_warehouse_available_when_warehouse_rows_exist(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-RP-01',
            'name' => 'Gudang Reorder',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'MAT-WH-LOW-01',
            'name' => 'Stok Gudang Rendah',
            'category' => 'Material',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'stock' => 500,
            'min_stock' => 30,
            'total_sold' => 0,
            'selling_price' => 10000,
        ]);
        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 8,
            'reserved_qty' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.purchasing.reorder-planning'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Purchasing/ReorderPlanning')
                ->where('reorderSuggestions.0.id', $product->id)
                ->where('reorderSuggestions.0.stock', 8)
                ->where('reorderSuggestions.0.stock_suggestion_qty', 22));

        $this
            ->actingAs($user)
            ->get(route('erp.purchasing.reorder-planning.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Purchasing/ReorderShow')
                ->where('detail.stock', 8)
                ->where('detail.stock_suggestion_qty', 22));
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);
    }

    private function createProduct(array $overrides = []): MasterProduct
    {
        return MasterProduct::query()->create(array_merge([
            'sku' => 'MAT-PO-001',
            'name' => 'Material PO',
            'category' => 'Material',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
            'stock' => 0,
            'min_stock' => 10,
            'total_sold' => 0,
            'selling_price' => 25000,
        ], $overrides));
    }
}
