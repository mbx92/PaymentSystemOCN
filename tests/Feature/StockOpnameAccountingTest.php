<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Core\Models\Company;
use App\ERP\Core\Models\FiscalPeriod;
use App\ERP\Inventory\Models\Warehouse;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class StockOpnameAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_opname_increase_posts_inventory_adjustment_journal(): void
    {
        $this->disableErpMiddleware();

        $company = Company::query()->create(['name' => 'OCN Main', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::query()->create([
            'company_id' => $company->id,
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'is_active' => true,
        ]);

        $inventoryAccount = $this->createAccount('1201', 'Persediaan Barang Dagang', 'asset', 'debit');
        $adjustmentAccount = $this->createAccount('5013', 'Beban Lain-lain', 'expense', 'debit');
        $product = $this->createProduct('OPN-ACC-001', 'Produk Opname Akunting', 25000, 5);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('erp.inventory.stock-opname.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'physical_stock' => 7,
                'stock_opname_date' => '2026-06-02',
                'note' => 'Tambah hasil hitung fisik',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $movement = ProductStockMovement::query()->firstOrFail();
        $entry = JournalEntry::query()
            ->where('source_module', 'stock_opname')
            ->where('source_reference', (string) $movement->id)
            ->firstOrFail();

        $this->assertSame($company->id, (int) $entry->company_id);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inventoryAccount->id,
            'debit' => '50000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $adjustmentAccount->id,
            'debit' => '0.00',
            'credit' => '50000.00',
        ]);
    }

    public function test_stock_opname_decrease_posts_inventory_adjustment_journal(): void
    {
        $this->disableErpMiddleware();

        $company = Company::query()->create(['name' => 'OCN Main', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::query()->create([
            'company_id' => $company->id,
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'is_active' => true,
        ]);

        $inventoryAccount = $this->createAccount('1201', 'Persediaan Barang Dagang', 'asset', 'debit');
        $adjustmentAccount = $this->createAccount('5013', 'Beban Lain-lain', 'expense', 'debit');
        $product = $this->createProduct('OPN-ACC-002', 'Produk Opname Akunting 2', 30000, 5);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('erp.inventory.stock-opname.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'physical_stock' => 3,
                'stock_opname_date' => '2026-06-02',
                'note' => 'Kurang dari catatan gudang',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $movement = ProductStockMovement::query()->firstOrFail();
        $entry = JournalEntry::query()
            ->where('source_module', 'stock_opname')
            ->where('source_reference', (string) $movement->id)
            ->firstOrFail();

        $this->assertSame($company->id, (int) $entry->company_id);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $adjustmentAccount->id,
            'debit' => '60000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inventoryAccount->id,
            'debit' => '0.00',
            'credit' => '60000.00',
        ]);
    }

    public function test_stock_opname_is_blocked_when_period_is_closed(): void
    {
        $this->disableErpMiddleware();

        $company = Company::query()->create(['name' => 'OCN Main', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::query()->create([
            'company_id' => $company->id,
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'is_active' => true,
        ]);

        $this->createAccount('1201', 'Persediaan Barang Dagang', 'asset', 'debit');
        $this->createAccount('5013', 'Beban Lain-lain', 'expense', 'debit');
        $product = $this->createProduct('OPN-ACC-003', 'Produk Opname Akunting 3', 15000, 5);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
            'reserved_qty' => 0,
        ]);

        FiscalPeriod::query()->create([
            'company_id' => $company->id,
            'name' => 'Tutup buku Juni 2026',
            'period_type' => 'monthly',
            'period_year' => 2026,
            'period_month' => 6,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => true,
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('erp.inventory.stock-opname.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'physical_stock' => 7,
                'stock_opname_date' => '2026-06-02',
                'note' => 'Tidak boleh lolos',
            ])
            ->assertSessionHasErrors('stock_opname_date');

        $this->assertDatabaseCount('product_stock_movements', 0);
        $this->assertDatabaseCount('journal_entries', 0);
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

    private function createAccount(string $code, string $name, string $type, string $normalBalance): Account
    {
        return Account::query()->updateOrCreate([
            'code' => $code,
        ], [
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_active' => true,
            'is_cash_bank' => false,
        ]);
    }

    private function createProduct(string $sku, string $name, float $unitCost, int $stock): MasterProduct
    {
        return MasterProduct::query()->create([
            'sku' => $sku,
            'name' => $name,
            'category' => 'Hardware',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => MasterProduct::PRODUCT_TYPE_FINISHED_GOODS,
            'status' => 'active',
            'selling_price' => 0,
            'unit_cost' => $unitCost,
            'stock' => $stock,
        ]);
    }
}
