<?php

namespace Tests\Feature;

use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\ProjectBudget;
use App\Models\User;
use App\Services\SupplierCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class SupplierCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_csv_is_parsed_with_supplier_codes_and_prices(): void
    {
        $csv = <<<'CSV'
PL TUNAS JAYA ELEKTRONIK,,,
,TIANDY,,
kode item,NAMA ITEM,JENIS,HARGA
IPCPTZTIA001,IPCAM PTZ TIANDY TC-H324S,IP CAMERA PTZ,"Rp2,800,000"
IPCTIA001,IPCAM TURRET INDOOR,IP CAMERA,"Rp440,000"
CSV;

        $service = app(SupplierCatalogService::class);
        $items = $service->parseCsv($csv, ['key' => 'tiandy', 'label' => 'TIANDY', 'gid' => '1']);

        $this->assertCount(2, $items);
        $this->assertSame('IPCPTZTIA001', $items[0]['code']);
        $this->assertSame('IPCAM PTZ TIANDY TC-H324S', $items[0]['name']);
        $this->assertSame(2800000.0, $items[0]['supplier_price']);
        $this->assertSame('tiandy:IPCPTZTIA001', $items[0]['ref']);
    }

    public function test_mark_deal_promotes_catalog_items_to_master_products(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $budget = ProjectBudget::query()->create([
            'name' => 'Budget CCTV Toko',
            'client_name' => 'PT ABC',
            'project_type' => 'cctv_installation',
            'estimated_value' => 3240000,
            'status' => 'draft',
        ]);

        $budget->items()->create([
            'catalog_sheet' => 'tiandy',
            'catalog_ref' => 'IPCTIA003',
            'catalog_category' => 'IP CAMERA',
            'item_type' => 'material',
            'name' => 'IPCAM TIANDY TC-C320N 2MP',
            'uom' => 'unit',
            'qty' => 2,
            'unit_cost' => 330000,
            'unit_price' => 450000,
            'sort_order' => 1,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('erp.projects.budgets.deal', $budget))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('master_products', [
            'sku' => 'IPCTIA003',
            'name' => 'IPCAM TIANDY TC-C320N 2MP',
            'unit_cost' => '330000.00',
            'selling_price' => '450000.00',
            'sales_channel' => 'project',
            'product_type' => MasterProduct::PRODUCT_TYPE_PROJECT_MATERIAL,
        ]);

        $budget->refresh();
        $this->assertSame('deal', $budget->status);
        $this->assertNotNull($budget->items()->first()->master_product_id);
    }

    public function test_catalog_api_returns_items_for_selected_brand(): void
    {
        $this->disableErpMiddleware();

        $this->mock(SupplierCatalogService::class, function ($mock): void {
            $mock->shouldReceive('itemsForSheet')
                ->once()
                ->with('tiandy', null)
                ->andReturn([
                    [
                        'ref' => 'tiandy:IPCTIA001',
                        'code' => 'IPCTIA001',
                        'name' => 'IPCAM TEST',
                        'category' => 'IP CAMERA',
                        'supplier_price' => 100000,
                        'sheet_key' => 'tiandy',
                        'sheet_label' => 'TIANDY',
                        'supplier_name' => 'PL TUNAS JAYA ELEKTRONIK',
                    ],
                ]);
        });

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/api/supplier-catalog/tiandy/items')
            ->assertOk()
            ->assertJsonPath('items.0.code', 'IPCTIA001')
            ->assertJsonPath('items.0.supplier_price', 100000);
    }

    public function test_sheet_rows_parser_handles_hikvision_column_layout(): void
    {
        $rows = [
            ['PL TUNAS JAYA ELEKTRONIK HIKVISION kode item', '', 'NAMA ITEM', 'HARGA'],
            ['ACHK003', 'DS-K1T342MFX (CARD,FINGER,FACE)', 'ACCES CONTROL', 'Rp2,341,000'],
        ];

        $service = app(SupplierCatalogService::class);
        $items = $service->parseSheetRows($rows, ['key' => 'hikvision', 'label' => 'HIKVISION']);

        $this->assertCount(1, $items);
        $this->assertSame('ACHK003', $items[0]['code']);
        $this->assertSame('DS-K1T342MFX (CARD,FINGER,FACE)', $items[0]['name']);
        $this->assertSame(2341000.0, $items[0]['supplier_price']);
    }

    public function test_catalog_sheets_api_returns_configured_brands(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/api/supplier-catalog/sheets')
            ->assertOk()
            ->assertJsonPath('sheets.0.key', 'hikvision')
            ->assertJsonPath('sheets.4.key', 'tiandy')
            ->assertJsonFragment(['label' => 'TIANDY']);
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
