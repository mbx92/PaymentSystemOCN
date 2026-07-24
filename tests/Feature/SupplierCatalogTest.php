<?php

namespace Tests\Feature;

use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\ProjectBudget;
use App\Models\SupplierCatalogItem;
use App\Models\User;
use App\Services\SupplierCatalogService;
use App\Services\SupplierCatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class SupplierCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_csv_is_parsed_with_supplier_codes_and_prices(): void
    {
        $csv = <<<'CSV'
PL TUNAS JAYA ELEKTRONIK kode item,TIANDY NAMA ITEM,JENIS,HARGA
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

    public function test_catalog_csv_skips_section_headers_without_item_code(): void
    {
        $csv = <<<'CSV'
PL TUNAS JAYA ELEKTRONIK KABEL LAN kode item,NAMA ITEM,JENIS,HARGA
KBLLNBLD001ROLL,KABEL LAN BELDEN CAT 6,KABEL LAN,"Rp2,850,000"
,KABEL RG,,
KBLRG59CLN001ROLL,KABEL RG 59 + POWER COLAN BLACK,KABEL RG 59 + POWER,"Rp900,000"
CSV;

        $service = app(SupplierCatalogService::class);
        $items = $service->parseCsv($csv, ['key' => 'kabel-lan-rg', 'label' => 'KABEL LAN / RG']);

        $this->assertCount(2, $items);
        $this->assertSame('KBLLNBLD001ROLL', $items[0]['code']);
        $this->assertSame('KBLRG59CLN001ROLL', $items[1]['code']);
    }

    public function test_sheet_rows_parser_handles_jenis_item_header(): void
    {
        $rows = [
            ['PL TUNAS JAYA ELEKTRONIK HIKVISION ANALOG CAMERA DAN DVR kode item', 'NAMA ITEM', 'JENIS ITEM ', 'HARGA'],
            ['CAMHK002', 'DS-2CE10DF0T-LPFS (HIKVISION CAMERA OUTDOOR 2MP AUDIO COLORVU)', 'CAMERA', 'Rp455,000'],
        ];

        $service = app(SupplierCatalogService::class);
        $items = $service->parseSheetRows($rows, ['key' => 'hikvision-analog-dvr', 'label' => 'HIKVISION ANALOG & DVR']);

        $this->assertCount(1, $items);
        $this->assertSame('CAMHK002', $items[0]['code']);
        $this->assertSame('CAMERA', $items[0]['category']);
        $this->assertSame(455000.0, $items[0]['supplier_price']);
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

        SupplierCatalogItem::query()->create([
            'ref' => 'tiandy:IPCTIA001',
            'sheet_key' => 'tiandy',
            'sheet_label' => 'TIANDY',
            'supplier_name' => 'PL TUNAS JAYA ELEKTRONIK',
            'code' => 'IPCTIA001',
            'name' => 'IPCAM TEST',
            'category' => 'IP CAMERA',
            'supplier_price' => 100000,
            'last_price' => null,
            'last_synced_at' => now(),
        ]);

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/api/supplier-catalog/tiandy/items')
            ->assertOk()
            ->assertJsonPath('items.0.code', 'IPCTIA001')
            ->assertJsonPath('items.0.supplier_price', 100000)
            ->assertJsonPath('items.0.last_price', null);
    }

    public function test_catalog_api_falls_back_to_remote_sheet_when_database_is_empty(): void
    {
        $this->disableErpMiddleware();

        $remoteItem = [
            'ref' => 'tiandy:IPCTIA009',
            'code' => 'IPCTIA009',
            'name' => 'IPCAM REMOTE',
            'category' => 'IP CAMERA',
            'supplier_price' => 250000.0,
            'sheet_key' => 'tiandy',
            'sheet_label' => 'TIANDY',
            'supplier_name' => 'PL TUNAS JAYA ELEKTRONIK',
        ];

        $this->mock(SupplierCatalogService::class, function ($mock) use ($remoteItem): void {
            $mock->shouldReceive('itemsForSheet')
                ->once()
                ->with('tiandy', null)
                ->andReturn([]);
            $mock->shouldReceive('fetchRemoteItemsForSheet')
                ->once()
                ->with('tiandy')
                ->andReturn([$remoteItem]);
            $mock->shouldReceive('lastSyncedAt')
                ->andReturn(null);
        });

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/api/supplier-catalog/tiandy/items')
            ->assertOk()
            ->assertJsonPath('source', 'remote')
            ->assertJsonPath('items.0.code', 'IPCTIA009')
            ->assertJsonPath('items.0.supplier_price', 250000);
    }

    public function test_catalog_sync_upserts_items_and_tracks_last_price(): void
    {
        $remoteItem = [
            'ref' => 'tiandy:IPCTIA001',
            'code' => 'IPCTIA001',
            'name' => 'IPCAM TEST',
            'category' => 'IP CAMERA',
            'supplier_price' => 100000.0,
            'sheet_key' => 'tiandy',
            'sheet_label' => 'TIANDY',
            'supplier_name' => 'PL TUNAS JAYA ELEKTRONIK',
        ];

        $this->mock(SupplierCatalogService::class, function ($mock) use ($remoteItem): void {
            $mock->shouldReceive('fetchRemoteItemsForSheet')
                ->twice()
                ->with('tiandy')
                ->andReturn([$remoteItem], [
                    array_merge($remoteItem, ['supplier_price' => 120000.0]),
                ]);
        });

        $sync = app(SupplierCatalogSyncService::class);

        $sync->syncSheet('tiandy');

        $this->assertDatabaseHas('supplier_catalog_items', [
            'ref' => 'tiandy:IPCTIA001',
            'supplier_price' => '100000.00',
            'last_price' => null,
        ]);

        $sync->syncSheet('tiandy');

        $this->assertDatabaseHas('supplier_catalog_items', [
            'ref' => 'tiandy:IPCTIA001',
            'supplier_price' => '120000.00',
            'last_price' => '100000.00',
        ]);
    }

    public function test_items_for_sheet_reads_from_database(): void
    {
        SupplierCatalogItem::query()->create([
            'ref' => 'tiandy:IPCTIA002',
            'sheet_key' => 'tiandy',
            'sheet_label' => 'TIANDY',
            'supplier_name' => 'PL TUNAS JAYA ELEKTRONIK',
            'code' => 'IPCTIA002',
            'name' => 'IPCAM DB',
            'category' => 'IP CAMERA',
            'supplier_price' => 440000,
            'last_price' => 400000,
            'last_synced_at' => now(),
        ]);

        $items = app(SupplierCatalogService::class)->itemsForSheet('tiandy');

        $this->assertCount(1, $items);
        $this->assertSame('IPCTIA002', $items[0]['code']);
        $this->assertSame(440000.0, $items[0]['supplier_price']);
        $this->assertSame(400000.0, $items[0]['last_price']);
        $this->assertNotNull($items[0]['last_synced_at']);
    }

    public function test_sheet_rows_parser_handles_hikvision_column_layout(): void
    {
        $rows = [
            ['PL TUNAS JAYA ELEKTRONIK HIKVISION ACCES CONTROL kode item', 'NAMA ITEM', 'JENIS', 'HARGA'],
            ['ACHK003', 'DS-K1T342MFX (CARD,FINGER,FACE)', 'ACCES CONTROL', 'Rp2,341,000'],
        ];

        $service = app(SupplierCatalogService::class);
        $items = $service->parseSheetRows($rows, ['key' => 'hikvision-access-poe', 'label' => 'HIKVISION ACCESS / POE / PSU']);

        $this->assertCount(1, $items);
        $this->assertSame('ACHK003', $items[0]['code']);
        $this->assertSame('DS-K1T342MFX (CARD,FINGER,FACE)', $items[0]['name']);
        $this->assertSame(2341000.0, $items[0]['supplier_price']);
    }

    public function test_sheet_rows_parser_handles_mislabeled_price_as_jenis(): void
    {
        $rows = [
            ['PL TUNAS JAYA ELEKTRONIK MERCUSYS kode item', 'NAMA ITEM', 'JENIS', 'JENIS'],
            ['RTRMCS001', 'ROUTER MERCUSYS MB110 N300 WIFI 4G LTE', 'ROUTER', 'Rp450,000'],
        ];

        $service = app(SupplierCatalogService::class);
        $items = $service->parseSheetRows($rows, ['key' => 'mercusys-huawei', 'label' => 'MERCUSYS & HUAWEI']);

        $this->assertCount(1, $items);
        $this->assertSame('RTRMCS001', $items[0]['code']);
        $this->assertSame('ROUTER', $items[0]['category']);
        $this->assertSame(450000.0, $items[0]['supplier_price']);
    }

    public function test_catalog_sheets_api_returns_configured_brands(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/api/supplier-catalog/sheets')
            ->assertOk()
            ->assertJsonPath('sheets.0.key', 'hikvision-analog-dvr')
            ->assertJsonFragment(['key' => 'tiandy', 'label' => 'TIANDY'])
            ->assertJsonFragment(['key' => 'kabel-lan-rg', 'label' => 'KABEL LAN / RG']);
    }

    public function test_catalog_sync_endpoint_syncs_all_sheets(): void
    {
        $this->disableErpMiddleware();

        $this->mock(SupplierCatalogSyncService::class, function ($mock): void {
            $mock->shouldReceive('syncAll')
                ->once()
                ->andReturn([
                    'sheets' => 3,
                    'created' => 2,
                    'updated' => 1,
                    'removed' => 0,
                    'failed' => [],
                ]);
        });

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson('/api/supplier-catalog/sync')
            ->assertOk()
            ->assertJsonPath('sheets', 3)
            ->assertJsonPath('created', 2)
            ->assertJsonPath('updated', 1);
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
