<?php

namespace Tests\Feature;

use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Purchasing\Models\PurchaseOrderLine;
use App\ERP\Purchasing\Models\Vendor;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetItem;
use App\Models\ProjectMaterial;
use App\Models\User;
use App\Services\ProjectReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class ProjectReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_report_includes_material_purchase_cost_in_cash_out_and_profit(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
            RoleOrPermissionMiddleware::class,
        ]);

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-PRJ',
            'name' => 'Gudang Project',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'MAT-PRJ-001',
            'name' => 'Material Project',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'name' => 'Project Numa',
            'client_name' => 'Client Numa',
            'project_type' => 'custom',
            'total_value' => 1500000,
            'status' => 'selesai',
            'finished_at' => '2026-05-23',
        ]);

        CashIn::query()->create([
            'project_id' => $project->id,
            'category' => 'pendapatan_project',
            'amount' => 1000000,
            'date' => '2026-05-23',
            'created_by' => $user->id,
        ]);

        CashOut::query()->create([
            'project_id' => $project->id,
            'category' => 'operasional',
            'amount' => 100000,
            'date' => '2026-05-23',
            'recipient_name' => 'Transport',
            'created_by' => $user->id,
        ]);

        ProjectMaterial::query()->create([
            'project_id' => $project->id,
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 2,
            'unit_cost' => 250000,
            'unit_price' => 450000,
            'status' => 'planned',
        ]);

        $request = Request::create('/laporan/project', 'GET');
        $request->setUserResolver(fn () => $user);

        $report = app(ProjectReportService::class)->build($request);
        $row = collect($report['projects']->items())->first();

        $this->assertSame(1000000.0, (float) $report['summary']['cash_in']);
        $this->assertSame(100000.0, (float) $report['summary']['operational_cash_out']);
        $this->assertSame(500000.0, (float) $report['summary']['purchase_cost']);
        $this->assertSame(600000.0, (float) $report['summary']['cash_out']);
        $this->assertSame(400000.0, (float) $report['summary']['profit']);
        $this->assertSame(100000.0, (float) $row['operational_cash_out']);
        $this->assertSame(500000.0, (float) $row['purchase_cost']);
        $this->assertSame(600000.0, (float) $row['cash_out']);
        $this->assertSame(400000.0, (float) $row['profit']);
    }

    public function test_project_report_prefers_recorded_purchase_cash_out_categories_for_purchase_card(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
            RoleOrPermissionMiddleware::class,
        ]);

        $user = User::factory()->create();
        $project = Project::query()->create([
            'name' => 'Project Bahan',
            'client_name' => 'Client Bahan',
            'project_type' => 'custom',
            'total_value' => 2000000,
            'status' => 'selesai',
            'finished_at' => '2026-05-23',
        ]);

        CashIn::query()->create([
            'project_id' => $project->id,
            'category' => 'pendapatan_project',
            'amount' => 1200000,
            'date' => '2026-05-23',
            'created_by' => $user->id,
        ]);

        CashOut::query()->create([
            'project_id' => $project->id,
            'category' => 'operasional',
            'amount' => 150000,
            'date' => '2026-05-23',
            'recipient_name' => 'Transport',
            'created_by' => $user->id,
        ]);

        CashOut::query()->create([
            'project_id' => $project->id,
            'category' => 'pembelian_bahan',
            'amount' => 350000,
            'date' => '2026-05-23',
            'recipient_name' => 'Supplier Bahan',
            'created_by' => $user->id,
        ]);

        $request = Request::create('/laporan/project', 'GET');
        $request->setUserResolver(fn () => $user);

        $report = app(ProjectReportService::class)->build($request);
        $row = collect($report['projects']->items())->firstWhere('name', 'Project Bahan');

        $this->assertSame(350000.0, (float) $row['purchase_cost']);
        $this->assertSame(150000.0, (float) $row['operational_cash_out']);
        $this->assertSame(500000.0, (float) $row['cash_out']);
        $this->assertSame(700000.0, (float) $row['profit']);
    }

    public function test_project_report_uses_converted_budget_item_cost_when_present(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
            RoleOrPermissionMiddleware::class,
        ]);

        $user = User::factory()->create();
        $project = Project::query()->create([
            'name' => 'Project Budget',
            'client_name' => 'Client Budget',
            'project_type' => 'custom',
            'total_value' => 0,
            'status' => 'selesai',
            'finished_at' => '2026-05-23',
        ]);

        CashIn::query()->create([
            'project_id' => $project->id,
            'category' => 'pendapatan_project',
            'amount' => 900000,
            'date' => '2026-05-23',
            'created_by' => $user->id,
        ]);

        $budget = ProjectBudget::query()->create([
            'name' => 'Budget CCTV',
            'client_name' => 'Client Budget',
            'converted_project_id' => $project->id,
            'project_type' => 'custom',
            'estimated_value' => 1500000,
            'status' => 'approved',
        ]);

        ProjectBudgetItem::query()->create([
            'project_budget_id' => $budget->id,
            'item_type' => 'material',
            'name' => 'Kabel',
            'uom' => 'roll',
            'qty' => 3,
            'unit_cost' => 200000,
            'unit_price' => 350000,
            'sort_order' => 1,
        ]);

        $request = Request::create('/laporan/project', 'GET');
        $request->setUserResolver(fn () => $user);

        $report = app(ProjectReportService::class)->build($request);
        $row = collect($report['projects']->items())->firstWhere('name', 'Project Budget');

        $this->assertSame(600000.0, (float) $row['purchase_cost']);
        $this->assertSame(600000.0, (float) $row['cash_out']);
        $this->assertSame(300000.0, (float) $row['profit']);
    }

    public function test_project_report_falls_back_to_latest_purchase_order_price_when_material_unit_cost_is_zero(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
            RoleOrPermissionMiddleware::class,
        ]);

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-FB',
            'name' => 'Gudang Fallback',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'MAT-FB-001',
            'name' => 'Material Fallback',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => 'project_material',
            'status' => 'active',
        ]);
        $vendor = Vendor::query()->create([
            'code' => 'SUP-FB',
            'name' => 'Supplier Fallback',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);
        $purchaseOrder = PurchaseOrder::query()->create([
            'number' => 'PO-FB-001',
            'vendor_id' => $vendor->id,
            'order_date' => '2026-05-20',
            'total_amount' => 360000,
            'status' => 'posted',
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'master_product_id' => $product->id,
            'qty' => 2,
            'received_qty' => 0,
            'unit_price' => 180000,
            'line_total' => 360000,
        ]);

        $project = Project::query()->create([
            'name' => 'Project Fallback PO',
            'client_name' => 'Client Fallback',
            'project_type' => 'custom',
            'total_value' => 0,
            'status' => 'selesai',
            'finished_at' => '2026-05-23',
        ]);

        CashIn::query()->create([
            'project_id' => $project->id,
            'category' => 'pendapatan_project',
            'amount' => 800000,
            'date' => '2026-05-23',
            'created_by' => $user->id,
        ]);

        ProjectMaterial::query()->create([
            'project_id' => $project->id,
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 2,
            'unit_cost' => 0,
            'unit_price' => 350000,
            'status' => 'planned',
        ]);

        $request = Request::create('/laporan/project', 'GET');
        $request->setUserResolver(fn () => $user);

        $report = app(ProjectReportService::class)->build($request);
        $row = collect($report['projects']->items())->firstWhere('name', 'Project Fallback PO');

        $this->assertSame(360000.0, (float) $row['purchase_cost']);
        $this->assertSame(360000.0, (float) $row['cash_out']);
        $this->assertSame(440000.0, (float) $row['profit']);
    }
}
