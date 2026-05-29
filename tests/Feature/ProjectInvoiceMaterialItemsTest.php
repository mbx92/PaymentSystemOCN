<?php

namespace Tests\Feature;

use App\ERP\Inventory\Models\Warehouse;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class ProjectInvoiceMaterialItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_project_materials_appear_on_sales_note_and_invoice_amount(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);

        $user = User::factory()->create();
        $warehouse = Warehouse::create([
            'code' => 'WH-INV',
            'name' => 'Main',
            'is_active' => true,
        ]);

        $product = MasterProduct::create([
            'sku' => 'CAM-INV-01',
            'name' => 'Kamera Invoice',
            'category' => 'CCTV',
            'uom' => 'unit',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
            'selling_price' => 500000,
        ]);

        MasterProductWarehouseStock::create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
            'reserved_qty' => 0,
        ]);

        $project = Project::query()->create([
            'name' => 'CCTV Tanpa Budget',
            'client_name' => 'Client Invoice',
            'project_type' => 'cctv_installation',
            'total_value' => 0,
            'status' => 'selesai',
            'finished_at' => '2026-05-17',
        ]);

        ProjectMaterial::query()->create([
            'project_id' => $project->id,
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 3,
            'unit_cost' => 400000,
            'unit_price' => 900000,
            'status' => 'planned',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.sales.project-invoices.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('invoice.amount', 2700000)
                ->where('invoice.line_items.0.name', 'Kamera Invoice')
                ->where('invoice.line_items.0.qty', 3)
                ->where('invoice.line_items.0.unit_price', 900000)
                ->where('invoice.line_items.0.subtotal', 2700000));

        $this
            ->actingAs($user)
            ->get(route('erp.sales.project-invoices.sales-note', $project))
            ->assertOk()
            ->assertDownload();

        $this
            ->actingAs($user)
            ->get(route('erp.sales.project-invoices.download', $project))
            ->assertOk()
            ->assertDownload();
    }
}
