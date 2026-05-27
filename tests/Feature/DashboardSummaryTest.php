<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Core\Models\Company;
use App\ERP\Purchasing\Models\Vendor;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\AccountingInventoryRecord;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\PaymentMethod;
use App\Models\PosSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_matches_accounting_cashflow_sources_for_current_company(): void
    {
        $this->disableErpMiddleware();

        $year = now()->year;
        $date = sprintf('%d-05-20', $year);

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

        $user = User::factory()->create(['company_id' => $companyA->id]);
        $otherUser = User::factory()->create(['company_id' => $companyB->id]);
        $cashier = User::factory()->create(['company_id' => $companyA->id]);

        $cashAccount = Account::query()->create([
            'code' => '1001',
            'name' => 'Kas Utama',
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
        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'cash',
            'name' => 'Cash',
            'status' => 'active',
        ]);

        $cashInJournal = JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-CI-A',
            'entry_date' => $date,
            'description' => 'Kas masuk A',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_in',
            'source_reference' => 'ci-a',
        ]);
        CashIn::query()->create([
            'cash_account_id' => $cashAccount->id,
            'payment_method_id' => $paymentMethod->id,
            'category' => 'pendapatan_jasa',
            'amount' => 100000,
            'date' => $date,
            'created_by' => $user->id,
            'journal_entry_id' => $cashInJournal->id,
        ]);

        $cashOutJournal = JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-CO-A',
            'entry_date' => $date,
            'description' => 'Kas keluar A',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_out',
            'source_reference' => 'co-a',
        ]);
        CashOut::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'operasional',
            'amount' => 20000,
            'date' => $date,
            'recipient_name' => 'Vendor A',
            'created_by' => $user->id,
            'journal_entry_id' => $cashOutJournal->id,
        ]);

        PosSale::query()->create([
            'number' => 'POS-A-001',
            'sales_channel' => 'retail',
            'payment_method_id' => $paymentMethod->id,
            'gross_total' => 50000,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 50000,
            'cash_paid' => 50000,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => $date.' 10:00:00',
            'sold_by' => $cashier->id,
        ]);
        JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-POS-A',
            'entry_date' => $date,
            'description' => 'Penjualan POS A',
            'status' => DocumentStatus::Posted,
            'source_module' => 'pos_sale',
            'source_reference' => 'POS-A-001',
        ]);

        $vendor = Vendor::query()->create([
            'code' => 'SUP-A',
            'name' => 'Supplier A',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);
        $payable = Payable::query()->create([
            'vendor_id' => $vendor->id,
            'bill_no' => 'BILL-A-001',
            'bill_date' => $date,
            'due_date' => $date,
            'amount' => 10000,
            'paid_amount' => 0,
            'status' => DocumentStatus::Posted,
        ]);
        $supplierPaymentJournal = JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-AP-A',
            'entry_date' => $date,
            'description' => 'Pembayaran supplier A',
            'status' => DocumentStatus::Posted,
            'source_module' => 'supplier_payment',
            'source_reference' => 'BILL-A-001',
        ]);
        PayablePayment::query()->create([
            'payable_id' => $payable->id,
            'payment_date' => $date,
            'amount' => 10000,
            'cash_account_id' => $cashAccount->id,
            'journal_entry_id' => $supplierPaymentJournal->id,
            'paid_by' => $user->id,
        ]);

        $inventoryJournal = JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-INV-A',
            'entry_date' => $date,
            'description' => 'Inventaris A',
            'status' => DocumentStatus::Posted,
            'source_module' => 'inventory_acquisition',
            'source_reference' => 'INV-A-001',
        ]);
        AccountingInventoryRecord::query()->create([
            'item_name' => 'iPad Stand',
            'qty' => 1,
            'amount' => 5000,
            'acquisition_date' => $date,
            'asset_account_id' => $assetAccount->id,
            'cash_account_id' => $cashAccount->id,
            'journal_entry_id' => $inventoryJournal->id,
            'created_by' => $user->id,
        ]);

        $otherJournal = JournalEntry::query()->create([
            'company_id' => $companyB->id,
            'entry_no' => 'JE-CI-B',
            'entry_date' => $date,
            'description' => 'Kas masuk B',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_in',
            'source_reference' => 'ci-b',
        ]);
        CashIn::query()->create([
            'cash_account_id' => $cashAccount->id,
            'payment_method_id' => $paymentMethod->id,
            'category' => 'pendapatan_jasa',
            'amount' => 999999,
            'date' => $date,
            'created_by' => $otherUser->id,
            'journal_entry_id' => $otherJournal->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['year' => $year]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('stats.total_income', 150000)
                ->where('stats.total_expense', 35000)
                ->where('stats.net_cashflow', 115000)
                ->where('monthlyData.4.income', 150000)
                ->where('monthlyData.4.expense', 35000)
                ->where('monthlyData.4.net', 115000)
            );
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
