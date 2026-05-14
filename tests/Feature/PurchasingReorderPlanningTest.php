<?php

namespace Tests\Feature;

use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Purchasing\Models\Vendor;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
            'lead_time_days' => 1,
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

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ErpMaintenanceMode::class,
            \App\Http\Middleware\LogErpActivity::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }

    private function createProduct(): MasterProduct
    {
        return MasterProduct::query()->create([
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
            'lead_time_days' => 7,
            'selling_price' => 25000,
        ]);
    }
}
