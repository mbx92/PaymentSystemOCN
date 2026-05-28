<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\CoaSetting;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Core\Models\Company;
use App\ERP\Purchasing\Models\GoodsReceipt;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Purchasing\Models\Vendor;
use App\ERP\Inventory\Models\Warehouse;
use App\Models\CashIn;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountingUtilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_utilities_can_move_journal_entries_between_companies(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $companyA = Company::query()->create(['name' => 'Usaha 1', 'is_active' => true]);
        $companyB = Company::query()->create(['name' => 'Usaha 2', 'is_active' => true]);
        $account = Account::query()->create([
            'code' => '1001',
            'name' => 'Kas',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        $entry = JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-TEST-001',
            'entry_date' => '2026-05-14',
            'description' => 'Transaksi test',
            'status' => 'posted',
            'source_module' => 'cash_in',
            'source_reference' => '1',
        ]);
        $entry->lines()->create([
            'account_id' => $account->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.utilities', ['company_id' => $companyA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Utilities')
                ->where('entries.data.0.entry_no', 'JE-TEST-001')
                ->where('entries.data.0.company_name', 'Usaha 1')
                ->has('companies', 3)
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.move-journals'), [
                'target_company_id' => $companyB->id,
                'journal_entry_ids' => [$entry->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'company_id' => $companyB->id,
        ]);
    }

    public function test_accounting_utilities_can_backfill_cash_account_ids_from_journal(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = Account::query()->updateOrCreate(['code' => '1101'], [
            'name' => 'Kas Utama',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $revenue = Account::query()->updateOrCreate(['code' => '4003'], [
            'name' => 'Pendapatan Project',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);

        $entry = JournalEntry::query()->create([
            'entry_no' => 'JE-INV-001',
            'entry_date' => '2026-05-14',
            'description' => 'Pembayaran invoice',
            'status' => 'posted',
            'source_module' => 'project_invoice_payment',
            'source_reference' => 'pay-1',
        ]);
        $entry->lines()->createMany([
            ['account_id' => $cash->id, 'debit' => 500000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500000],
        ]);

        $cashIn = CashIn::query()->create([
            'amount' => 500000,
            'date' => '2026-05-14',
            'category' => 'project_payment',
            'journal_entry_id' => $entry->id,
            'cash_account_id' => null,
            'created_by' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.utilities'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Utilities')
                ->where('cashAccountBackfill.cash_in_ready', 1)
                ->where('cashAccountBackfill.cash_in_pending', 1)
                ->has('cashAccountBackfill.samples', 1)
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.backfill-cash-accounts'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('cash_in', [
            'id' => $cashIn->id,
            'cash_account_id' => $cash->id,
        ]);
    }

    public function test_accounting_utilities_can_correct_pos_channel_payable_from_latest_coa_setting(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $company = Company::query()->create(['name' => 'Usaha 1', 'is_active' => true]);
        $cash = Account::query()->updateOrCreate(['code' => '1001'], [
            'code' => '1001',
            'name' => 'Kas',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        $revenue = Account::query()->updateOrCreate(['code' => '4002'], [
            'code' => '4002',
            'name' => 'Pendapatan Penjualan POS',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);
        $expense = Account::query()->updateOrCreate(['code' => '5014'], [
            'code' => '5014',
            'name' => 'Beban Marketing & Iklan',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        $payable = Account::query()->updateOrCreate(['code' => '2007'], [
            'code' => '2007',
            'name' => 'Hutang Estimasi Biaya Channel',
            'type' => 'liability',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);

        CoaSetting::query()->create(['key' => 'pos_sale_sales_channel_admin_expense', 'account_id' => $expense->id]);
        CoaSetting::query()->create(['key' => 'pos_sale_sales_channel_admin_payable', 'account_id' => $payable->id]);

        $entry = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-POS-001',
            'entry_date' => '2026-05-14',
            'description' => 'Penjualan POS TRX-001',
            'status' => 'posted',
            'source_module' => 'pos_sale',
            'source_reference' => 'TRX-001',
        ]);
        $entry->lines()->createMany([
            ['account_id' => $cash->id, 'debit' => 60000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 60000],
            ['account_id' => $expense->id, 'debit' => 6616, 'credit' => 0],
            ['account_id' => $expense->id, 'debit' => 0, 'credit' => 6616],
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.utilities', ['q' => 'JE-POS-001']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Utilities')
                ->where('posChannelCorrection.can_correct', true)
                ->where('posChannelCorrection.candidate_count', 1)
                ->where('posChannelCorrection.payable_account', '2007 - Hutang Estimasi Biaya Channel')
                ->where('posChannelCorrection.candidates.0.entry_no', 'JE-POS-001')
                ->where('posChannelCorrection.candidates.0.candidate_amount', 6616)
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.correct-pos-channel-payable'), [
                'journal_entry_ids' => [$entry->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $expense->id,
            'debit' => '6616.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $payable->id,
            'debit' => '0.00',
            'credit' => '6616.00',
        ]);
    }

    public function test_accounting_utilities_can_reverse_selected_journal_entry_sides(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $company = Company::query()->create(['name' => 'Usaha Reverse', 'is_active' => true]);
        $cash = Account::query()->create([
            'code' => '1102',
            'name' => 'Bank BRI',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $equipment = Account::query()->create([
            'code' => '1401',
            'name' => 'Peralatan',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        $equity = Account::query()->create([
            'code' => '3001',
            'name' => 'Modal Pemilik',
            'type' => 'equity',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);

        $entry = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-OPENING-REV',
            'entry_date' => '2025-12-31',
            'description' => 'Saldo awal terbalik',
            'status' => 'posted',
            'source_module' => 'opening_balance',
            'source_reference' => 'OPENING-REV',
        ]);
        $entry->lines()->createMany([
            ['account_id' => $equity->id, 'debit' => 40000000, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 32000000],
            ['account_id' => $equipment->id, 'debit' => 0, 'credit' => 8000000],
        ]);

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.reverse-journal-sides'), [
                'journal_entry_ids' => [$entry->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $equity->id,
            'debit' => '0.00',
            'credit' => '40000000.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => '32000000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $equipment->id,
            'debit' => '8000000.00',
            'credit' => '0.00',
        ]);
    }

    public function test_accounting_utilities_can_sync_supplier_payment_company_to_payable_company(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $companyOcn = Company::query()->create(['name' => 'OC Networks', 'is_active' => true]);
        $companyNuma = Company::query()->create(['name' => 'Numa Packaging', 'is_active' => true]);
        $cashAccount = Account::query()->create([
            'code' => '1102',
            'name' => 'Bank BRI',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $vendor = Vendor::query()->create([
            'code' => 'SUP-NUMA',
            'name' => 'Supplier Numa',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-NUMA',
            'company_id' => $companyNuma->id,
            'name' => 'Gudang Numa',
            'is_active' => true,
        ]);
        $po = PurchaseOrder::query()->create([
            'number' => 'PO-NUMA-001',
            'vendor_id' => $vendor->id,
            'order_date' => '2026-01-30',
            'eta_date' => '2026-01-31',
            'total_amount' => 500000,
            'status' => 'approved',
        ]);
        $receipt = GoodsReceipt::query()->create([
            'number' => 'GR-NUMA-001',
            'purchase_order_id' => $po->id,
            'received_date' => '2026-01-31',
            'warehouse_id' => $warehouse->id,
            'warehouse_name' => $warehouse->name,
            'status' => 'approved',
        ]);
        $payableEntry = JournalEntry::query()->create([
            'company_id' => $companyNuma->id,
            'entry_no' => 'JE-AP-NUMA-001',
            'entry_date' => '2026-01-31',
            'description' => 'Hutang supplier Numa',
            'status' => 'posted',
            'source_module' => 'purchasing',
            'source_reference' => $receipt->number,
        ]);
        $payable = Payable::query()->create([
            'vendor_id' => $vendor->id,
            'purchase_order_id' => $po->id,
            'goods_receipt_id' => $receipt->id,
            'bill_no' => 'BILL-NUMA-001',
            'bill_date' => '2026-01-31',
            'due_date' => '2026-02-14',
            'amount' => 500000,
            'paid_amount' => 500000,
            'status' => 'paid',
            'journal_entry_id' => $payableEntry->id,
        ]);
        $paymentEntry = JournalEntry::query()->create([
            'company_id' => $companyOcn->id,
            'entry_no' => 'JE-PAY-NUMA-001',
            'entry_date' => '2026-01-31',
            'description' => 'Bayar supplier salah usaha',
            'status' => 'posted',
            'source_module' => 'supplier_payment',
            'source_reference' => $payable->bill_no,
        ]);
        PayablePayment::query()->create([
            'payable_id' => $payable->id,
            'payment_date' => '2026-01-31',
            'amount' => 500000,
            'cash_account_id' => $cashAccount->id,
            'journal_entry_id' => $paymentEntry->id,
            'paid_by' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.utilities', ['company_id' => $companyNuma->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Utilities')
                ->where('supplierPaymentCompanySync.entry_count', 1)
                ->where('supplierPaymentCompanySync.candidate_count', 1)
                ->where('supplierPaymentCompanySync.samples.0.bill_no', 'BILL-NUMA-001')
                ->where('supplierPaymentCompanySync.samples.0.current_company_name', 'OC Networks')
                ->where('supplierPaymentCompanySync.samples.0.expected_company_name', 'Numa Packaging')
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.sync-supplier-payment-companies'), [
                'company_id' => $companyNuma->id,
                'date_from' => '2026-01-01',
                'date_to' => '2026-12-31',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('journal_entries', [
            'id' => $paymentEntry->id,
            'company_id' => $companyNuma->id,
        ]);
    }

    public function test_accounting_utilities_can_reassign_cash_accounts_and_journal_lines(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $kas = Account::query()->updateOrCreate(['code' => '1001'], [
            'name' => 'Kas',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $bca = Account::query()->updateOrCreate(['code' => '1002'], [
            'name' => 'Bank BCA',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $revenue = Account::query()->updateOrCreate(['code' => '4003'], [
            'name' => 'Pendapatan Project',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);

        $entry = JournalEntry::query()->create([
            'entry_no' => 'JE-REASSIGN-001',
            'entry_date' => '2026-05-14',
            'description' => 'Pembayaran invoice project',
            'status' => 'posted',
            'source_module' => 'project_invoice_payment',
            'source_reference' => '1',
        ]);
        $debitLine = $entry->lines()->create([
            'account_id' => $kas->id,
            'debit' => 750000,
            'credit' => 0,
        ]);
        $entry->lines()->create([
            'account_id' => $revenue->id,
            'debit' => 0,
            'credit' => 750000,
        ]);

        $cashIn = CashIn::query()->create([
            'amount' => 750000,
            'date' => '2026-05-14',
            'category' => 'pendapatan_project',
            'journal_entry_id' => $entry->id,
            'cash_account_id' => $kas->id,
            'created_by' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.utilities', ['reassign_from' => $kas->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Utilities')
                ->where('cashAccountReassignment.cash_in_count', 1)
                ->where('cashAccountReassignment.journal_lines_count', 1)
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.reassign-cash-accounts'), [
                'from_account_id' => $kas->id,
                'to_account_id' => $bca->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('cash_in', [
            'id' => $cashIn->id,
            'cash_account_id' => $bca->id,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'id' => $debitLine->id,
            'account_id' => $bca->id,
        ]);
    }

    public function test_accounting_utilities_can_sync_inventory_reserved_stock(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-UTIL',
            'name' => 'Gudang Utilitas',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'MAT-UTIL-01',
            'name' => 'Material Utility',
            'category' => 'Material',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => MasterProduct::PRODUCT_TYPE_PROJECT_MATERIAL,
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'name' => 'Project Utility',
            'client_name' => 'Client',
            'total_value' => 1000000,
            'status' => 'selesai',
            'finished_at' => '2026-05-19',
        ]);

        ProjectMaterial::query()->create([
            'project_id' => $project->id,
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => 4,
            'reserved_qty' => 4,
            'issued_qty' => 0,
            'status' => 'ready',
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 4,
            'reserved_qty' => 4,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.utilities'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Utilities')
                ->where('inventoryReservationSync.warehouse_rows_updated', 1)
                ->where('inventoryReservationSync.warehouse_rows_cleared', 1)
                ->where('inventoryReservationSync.total_reserved_before', 4)
                ->where('inventoryReservationSync.total_reserved_after', 0)
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.sync-inventory-reservations'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('master_product_warehouse_stocks', [
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'reserved_qty' => '0.00',
        ]);
    }

    public function test_accounting_utilities_can_rebuild_inventory_stock_from_movements(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-REBUILD',
            'name' => 'Gudang Rebuild',
            'is_active' => true,
        ]);
        $product = MasterProduct::query()->create([
            'sku' => 'MAT-REBUILD-01',
            'name' => 'Material Rebuild',
            'category' => 'Material',
            'uom' => 'pcs',
            'sales_channel' => 'project',
            'product_type' => MasterProduct::PRODUCT_TYPE_PROJECT_MATERIAL,
            'status' => 'active',
            'stock' => 0,
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);

        ProductStockMovement::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_date' => '2026-05-19',
            'movement_type' => 'opname_in',
            'qty' => 7,
            'note' => 'Opname masuk',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.utilities'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Utilities')
                ->where('inventoryStockRebuild.warehouse_rows_updated', 1)
                ->where('inventoryStockRebuild.total_qty_before', 0)
                ->where('inventoryStockRebuild.total_qty_after', 7)
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.rebuild-inventory-stocks'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

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

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ErpMaintenanceMode::class,
            \App\Http\Middleware\LogErpActivity::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
            \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    }
}
