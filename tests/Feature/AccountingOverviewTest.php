<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Core\Models\Company;
use App\ERP\Purchasing\Models\Vendor;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\AccountingInventoryRecord;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\PosSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class AccountingOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_overview_renders_accounting_dashboard_payload(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $company = Company::query()->create([
            'name' => 'OCN Software',
            'legal_name' => 'PT OCN Software',
            'is_active' => true,
        ]);
        $cashAccount = Account::query()->create([
            'code' => '1101',
            'name' => 'Kas Utama',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $revenueAccount = Account::query()->create([
            'code' => '4101',
            'name' => 'Pendapatan Jasa',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
            'is_cash_bank' => false,
        ]);
        $expenseAccount = Account::query()->create([
            'code' => '5101',
            'name' => 'Beban Operasional',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => false,
        ]);

        $cashInJournal = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-IN-001',
            'entry_date' => now()->toDateString(),
            'description' => 'Kas masuk overview',
            'status' => 'posted',
            'source_module' => 'cash_in',
            'source_reference' => 'cash-in-001',
        ]);
        $cashInJournal->lines()->createMany([
            [
                'account_id' => $cashAccount->id,
                'description' => 'Kas masuk',
                'debit' => 5000000,
                'credit' => 0,
            ],
            [
                'account_id' => $revenueAccount->id,
                'description' => 'Pendapatan jasa',
                'debit' => 0,
                'credit' => 5000000,
            ],
        ]);

        $cashOutJournal = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-OUT-001',
            'entry_date' => now()->toDateString(),
            'description' => 'Kas keluar overview',
            'status' => 'posted',
            'source_module' => 'cash_out',
            'source_reference' => 'cash-out-001',
        ]);
        $cashOutJournal->lines()->createMany([
            [
                'account_id' => $expenseAccount->id,
                'description' => 'Beban operasional',
                'debit' => 2000000,
                'credit' => 0,
            ],
            [
                'account_id' => $cashAccount->id,
                'description' => 'Kas keluar',
                'debit' => 0,
                'credit' => 2000000,
            ],
        ]);

        CashIn::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'project_payment',
            'amount' => 5000000,
            'date' => now()->toDateString(),
            'created_by' => $user->id,
            'journal_entry_id' => $cashInJournal->id,
        ]);

        CashOut::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'operasional',
            'amount' => 2000000,
            'date' => now()->toDateString(),
            'recipient_name' => 'Vendor',
            'created_by' => $user->id,
            'journal_entry_id' => $cashOutJournal->id,
        ]);

        $this->actingAs($user)
            ->get(route('erp.accounting.overview', ['company_id' => $company->id, 'year' => now()->year]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Overview')
                ->where('stats.cash_in_year', 5000000)
                ->where('stats.cash_out_year', 2000000)
                ->where('stats.net_year', 3000000)
                ->where('stats.cash_balance', 3000000)
                ->has('monthly_data', 12)
                ->has('cash_accounts', 1)
                ->has('company_summaries', 1)
                ->has('transaction_breakdown.labels', 2)
                ->has('transaction_highlights', 2)
            );
    }

    public function test_accounting_overview_filters_by_creator_company_when_journal_company_is_missing(): void
    {
        $this->disableErpMiddleware();

        $companyA = Company::query()->create([
            'name' => 'OCN A',
            'legal_name' => 'PT OCN A',
            'is_active' => true,
        ]);
        $companyB = Company::query()->create([
            'name' => 'OCN B',
            'legal_name' => 'PT OCN B',
            'is_active' => true,
        ]);
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $userB = User::factory()->create(['company_id' => $companyB->id]);
        $cashAccount = Account::query()->create([
            'code' => '1102',
            'name' => 'Kas Cabang',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);

        CashIn::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'pendapatan_jasa',
            'amount' => 3000000,
            'date' => now()->toDateString(),
            'created_by' => $userA->id,
        ]);
        CashOut::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'operasional',
            'amount' => 1000000,
            'date' => now()->toDateString(),
            'recipient_name' => 'Vendor A',
            'created_by' => $userA->id,
        ]);
        CashIn::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'pendapatan_jasa',
            'amount' => 900000,
            'date' => now()->toDateString(),
            'created_by' => $userB->id,
        ]);

        $this->actingAs($userA)
            ->get(route('erp.accounting.overview', ['company_id' => $companyA->id, 'year' => now()->year]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Overview')
                ->where('stats.cash_in_year', 3000000)
                ->where('stats.cash_out_year', 1000000)
                ->where('stats.net_year', 2000000)
                ->where('stats.company_count', 1)
            );
    }

    public function test_accounting_overview_includes_pos_only_company(): void
    {
        $this->disableErpMiddleware();

        $ocn = Company::query()->create([
            'name' => 'OC Network',
            'legal_name' => 'PT OC Network',
            'is_active' => true,
        ]);
        $numa = Company::query()->create([
            'name' => 'Numa Packaging',
            'legal_name' => 'PT Numa Packaging',
            'is_active' => true,
        ]);
        $viewer = User::factory()->create(['company_id' => $ocn->id]);
        $cashier = User::factory()->create(['company_id' => $numa->id]);
        $cashAccount = Account::query()->updateOrCreate(['code' => '1001'], [
            'code' => '1001',
            'name' => 'Kas POS',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $revenueAccount = Account::query()->updateOrCreate(['code' => '4002'], [
            'code' => '4002',
            'name' => 'Penjualan POS',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
            'is_cash_bank' => false,
        ]);

        PosSale::query()->create([
            'number' => 'POS-NUMA-001',
            'gross_total' => 750000,
            'discount_total' => 0,
            'grand_total' => 750000,
            'cash_paid' => 750000,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => now()->toDateTimeString(),
            'sold_by' => $cashier->id,
        ]);

        $journal = JournalEntry::query()->create([
            'company_id' => $numa->id,
            'entry_no' => 'JE-POS-NUMA-001',
            'entry_date' => now()->toDateString(),
            'description' => 'Penjualan POS Numa',
            'status' => 'posted',
            'source_module' => 'pos_sale',
            'source_reference' => 'POS-NUMA-001',
        ]);
        $journal->lines()->createMany([
            [
                'account_id' => $cashAccount->id,
                'description' => 'Kas masuk POS',
                'debit' => 750000,
                'credit' => 0,
            ],
            [
                'account_id' => $revenueAccount->id,
                'description' => 'Penjualan POS',
                'debit' => 0,
                'credit' => 750000,
            ],
        ]);

        $this->actingAs($viewer)
            ->get(route('erp.accounting.overview', ['company_id' => $numa->id, 'year' => now()->year]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Overview')
                ->where('stats.cash_in_year', 750000)
                ->where('stats.cash_out_year', 0)
                ->where('stats.net_year', 750000)
                ->where('stats.cash_balance', 750000)
                ->where('stats.company_count', 1)
                ->where('company_summaries.0.company_name', 'Numa Packaging')
            );
    }

    public function test_accounting_overview_reduces_net_cashflow_by_supplier_payments(): void
    {
        $this->disableErpMiddleware();

        $company = Company::query()->create([
            'name' => 'Numa Packaging',
            'legal_name' => 'PT Numa Packaging',
            'is_active' => true,
        ]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $cashier = User::factory()->create(['company_id' => $company->id]);
        $cashAccount = Account::query()->updateOrCreate(['code' => '1001'], [
            'code' => '1001',
            'name' => 'Kas POS',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $revenueAccount = Account::query()->updateOrCreate(['code' => '4002'], [
            'code' => '4002',
            'name' => 'Penjualan POS',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
            'is_cash_bank' => false,
        ]);

        PosSale::query()->create([
            'number' => 'POS-NUMA-002',
            'gross_total' => 750000,
            'discount_total' => 0,
            'grand_total' => 750000,
            'cash_paid' => 750000,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => now()->toDateTimeString(),
            'sold_by' => $cashier->id,
        ]);

        $posJournal = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-POS-NUMA-002',
            'entry_date' => now()->toDateString(),
            'description' => 'Penjualan POS Numa 2',
            'status' => 'posted',
            'source_module' => 'pos_sale',
            'source_reference' => 'POS-NUMA-002',
        ]);
        $posJournal->lines()->createMany([
            [
                'account_id' => $cashAccount->id,
                'description' => 'Kas masuk POS',
                'debit' => 750000,
                'credit' => 0,
            ],
            [
                'account_id' => $revenueAccount->id,
                'description' => 'Penjualan POS',
                'debit' => 0,
                'credit' => 750000,
            ],
        ]);

        $vendor = Vendor::query()->create([
            'code' => 'SUP-NUMA',
            'name' => 'Supplier Numa',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);
        $payable = Payable::query()->create([
            'vendor_id' => $vendor->id,
            'bill_no' => 'BILL-NUMA-001',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'amount' => 300000,
            'paid_amount' => 0,
            'status' => DocumentStatus::Posted,
        ]);
        $supplierJournal = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-AP-NUMA-001',
            'entry_date' => now()->toDateString(),
            'description' => 'Bayar supplier Numa',
            'status' => 'posted',
            'source_module' => 'supplier_payment',
            'source_reference' => 'BILL-NUMA-001',
        ]);
        PayablePayment::query()->create([
            'payable_id' => $payable->id,
            'payment_date' => now()->toDateString(),
            'amount' => 300000,
            'cash_account_id' => $cashAccount->id,
            'note' => 'Pembelian bahan',
            'journal_entry_id' => $supplierJournal->id,
            'paid_by' => $viewer->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('erp.accounting.overview', ['company_id' => $company->id, 'year' => now()->year]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Overview')
                ->where('stats.cash_in_year', 750000)
                ->where('stats.cash_out_year', 300000)
                ->where('stats.net_year', 450000)
                ->where('stats.cash_balance', 450000)
            );
    }

    public function test_accounting_overview_includes_inventory_outflow_in_cash_totals(): void
    {
        $this->disableErpMiddleware();

        $company = Company::query()->create([
            'name' => 'OCN Inventory',
            'legal_name' => 'PT OCN Inventory',
            'is_active' => true,
        ]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $cashAccount = Account::query()->create([
            'code' => '1001',
            'name' => 'Kas Besar',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $assetAccount = Account::query()->create([
            'code' => '1401',
            'name' => 'Peralatan',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => false,
        ]);

        $cashInJournal = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-INV-CASH-IN',
            'entry_date' => now()->toDateString(),
            'description' => 'Kas masuk inventory',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_in',
            'source_reference' => 'ci-inv',
        ]);
        CashIn::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'pendapatan_jasa',
            'amount' => 2000000,
            'date' => now()->toDateString(),
            'created_by' => $viewer->id,
            'journal_entry_id' => $cashInJournal->id,
        ]);

        $inventoryJournal = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-INV-OUT',
            'entry_date' => now()->toDateString(),
            'description' => 'Pembelian inventaris',
            'status' => DocumentStatus::Posted,
            'source_module' => 'accounting_inventory',
            'source_reference' => 'inv-001',
        ]);
        AccountingInventoryRecord::query()->create([
            'item_name' => 'Laptop Keuangan',
            'qty' => 1,
            'amount' => 500000,
            'acquisition_date' => now()->toDateString(),
            'asset_account_id' => $assetAccount->id,
            'cash_account_id' => $cashAccount->id,
            'journal_entry_id' => $inventoryJournal->id,
            'created_by' => $viewer->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('erp.accounting.overview', ['company_id' => $company->id, 'year' => now()->year]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Overview')
                ->where('stats.cash_in_year', 2000000)
                ->where('stats.cash_out_year', 500000)
                ->where('stats.net_year', 1500000)
                ->where('stats.cash_balance', 1500000)
            );
    }

    public function test_accounting_overview_cash_balance_uses_selected_year_period(): void
    {
        $this->disableErpMiddleware();

        $company = Company::query()->create([
            'name' => 'OCN Periodic',
            'legal_name' => 'PT OCN Periodic',
            'is_active' => true,
        ]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $cashAccount = Account::query()->create([
            'code' => '1101',
            'name' => 'Kas Utama',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);

        $previousYear = now()->year - 1;
        $currentYear = now()->year;

        $previousJournal = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-PREV-BAL',
            'entry_date' => $previousYear.'-12-15',
            'description' => 'Kas masuk tahun lalu',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_in',
            'source_reference' => 'prev-balance',
        ]);
        CashIn::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'pendapatan_jasa',
            'amount' => 10000000,
            'date' => $previousYear.'-12-15',
            'created_by' => $viewer->id,
            'journal_entry_id' => $previousJournal->id,
        ]);

        $currentJournalIn = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-CURR-IN',
            'entry_date' => $currentYear.'-05-10',
            'description' => 'Kas masuk tahun ini',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_in',
            'source_reference' => 'curr-balance-in',
        ]);
        CashIn::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'pendapatan_jasa',
            'amount' => 3000000,
            'date' => $currentYear.'-05-10',
            'created_by' => $viewer->id,
            'journal_entry_id' => $currentJournalIn->id,
        ]);

        $currentJournalOut = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-CURR-OUT',
            'entry_date' => $currentYear.'-05-11',
            'description' => 'Kas keluar tahun ini',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_out',
            'source_reference' => 'curr-balance-out',
        ]);
        CashOut::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'operasional',
            'amount' => 1000000,
            'date' => $currentYear.'-05-11',
            'recipient_name' => 'Vendor',
            'created_by' => $viewer->id,
            'journal_entry_id' => $currentJournalOut->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('erp.accounting.overview', ['company_id' => $company->id, 'year' => $currentYear]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Overview')
                ->where('stats.cash_in_year', 3000000)
                ->where('stats.cash_out_year', 1000000)
                ->where('stats.net_year', 2000000)
                ->where('stats.cash_balance', 2000000)
                ->where('cash_accounts.0.balance', 2000000)
            );
    }

    public function test_accounting_overview_includes_opening_cash_balance_in_ending_cash_position(): void
    {
        $this->disableErpMiddleware();

        $company = Company::query()->create([
            'name' => 'OCN Opening Overview',
            'legal_name' => 'PT OCN Opening Overview',
            'is_active' => true,
        ]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $cashAccount = Account::query()->create([
            'code' => '1101',
            'name' => 'Kas Utama',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $equityAccount = Account::query()->create([
            'code' => '3001',
            'name' => 'Modal Pemilik',
            'type' => 'equity',
            'normal_balance' => 'credit',
            'is_active' => true,
            'is_cash_bank' => false,
        ]);

        $previousYear = now()->year - 1;
        $currentYear = now()->year;

        $openingEntry = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-OPENING-OV',
            'entry_date' => $previousYear.'-12-31',
            'description' => 'Saldo awal overview',
            'status' => DocumentStatus::Posted,
            'source_module' => 'opening_balance',
            'source_reference' => 'opening-overview',
        ]);
        $openingEntry->lines()->createMany([
            [
                'account_id' => $cashAccount->id,
                'description' => 'Kas awal',
                'debit' => 2000000,
                'credit' => 0,
            ],
            [
                'account_id' => $equityAccount->id,
                'description' => 'Modal awal',
                'debit' => 0,
                'credit' => 2000000,
            ],
        ]);

        $currentJournalIn = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-CURR-IN-OV',
            'entry_date' => $currentYear.'-05-10',
            'description' => 'Kas masuk tahun ini',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_in',
            'source_reference' => 'curr-opening-in',
        ]);
        CashIn::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'pendapatan_jasa',
            'amount' => 3000000,
            'date' => $currentYear.'-05-10',
            'created_by' => $viewer->id,
            'journal_entry_id' => $currentJournalIn->id,
        ]);

        $currentJournalOut = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-CURR-OUT-OV',
            'entry_date' => $currentYear.'-05-11',
            'description' => 'Kas keluar tahun ini',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_out',
            'source_reference' => 'curr-opening-out',
        ]);
        CashOut::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'operasional',
            'amount' => 1000000,
            'date' => $currentYear.'-05-11',
            'recipient_name' => 'Vendor',
            'created_by' => $viewer->id,
            'journal_entry_id' => $currentJournalOut->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('erp.accounting.overview', ['company_id' => $company->id, 'year' => $currentYear]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Overview')
                ->where('stats.cash_in_year', 3000000)
                ->where('stats.cash_out_year', 1000000)
                ->where('stats.net_year', 2000000)
                ->where('stats.opening_cash_balance', 2000000)
                ->where('stats.ending_cash_balance', 4000000)
                ->where('stats.cash_balance', 4000000)
                ->where('cash_accounts.0.opening_balance', 2000000)
                ->where('cash_accounts.0.ending_balance', 4000000)
            );
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
}
