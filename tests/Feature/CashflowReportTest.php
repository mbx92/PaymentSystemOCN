<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Core\Models\Company;
use App\ERP\Purchasing\Models\Vendor;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\PaymentMethod;
use App\Models\PosSale;
use App\Models\User;
use App\Services\CashflowReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CashflowReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashflow_report_includes_pos_and_supplier_payment_with_company_filter(): void
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
        $user = User::factory()->create(['company_id' => $companyA->id]);
        $cashierA = User::factory()->create(['company_id' => $companyA->id, 'name' => 'Kasir A']);
        $cashierB = User::factory()->create(['company_id' => $companyB->id, 'name' => 'Kasir B']);
        $cashAccount = Account::query()->create([
            'code' => '1001',
            'name' => 'Kas',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'cash',
            'name' => 'Cash',
            'status' => 'active',
        ]);

        $manualInJournal = JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-CI-A',
            'entry_date' => '2026-05-20',
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
            'date' => '2026-05-20',
            'created_by' => $user->id,
            'journal_entry_id' => $manualInJournal->id,
        ]);

        $manualOutJournal = JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-CO-A',
            'entry_date' => '2026-05-20',
            'description' => 'Kas keluar A',
            'status' => DocumentStatus::Posted,
            'source_module' => 'cash_out',
            'source_reference' => 'co-a',
        ]);
        CashOut::query()->create([
            'cash_account_id' => $cashAccount->id,
            'category' => 'operasional',
            'amount' => 50000,
            'date' => '2026-05-20',
            'recipient_name' => 'Vendor Umum',
            'created_by' => $user->id,
            'journal_entry_id' => $manualOutJournal->id,
        ]);

        PosSale::query()->create([
            'number' => 'POS-A-001',
            'sales_channel' => 'retail',
            'payment_method_id' => $paymentMethod->id,
            'gross_total' => 200000,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 200000,
            'cash_paid' => 200000,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => '2026-05-20 10:00:00',
            'sold_by' => $cashierA->id,
        ]);
        JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-POS-A',
            'entry_date' => '2026-05-20',
            'description' => 'Penjualan POS A',
            'status' => DocumentStatus::Posted,
            'source_module' => 'pos_sale',
            'source_reference' => 'POS-A-001',
        ]);

        $vendorA = Vendor::query()->create([
            'code' => 'SUP-A',
            'name' => 'Supplier A',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);
        $payableA = Payable::query()->create([
            'vendor_id' => $vendorA->id,
            'bill_no' => 'BILL-A-001',
            'bill_date' => '2026-05-20',
            'due_date' => '2026-06-03',
            'amount' => 40000,
            'paid_amount' => 0,
            'status' => DocumentStatus::Posted,
        ]);
        $supplierPaymentJournal = JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-AP-A',
            'entry_date' => '2026-05-20',
            'description' => 'Pembayaran supplier A',
            'status' => DocumentStatus::Posted,
            'source_module' => 'supplier_payment',
            'source_reference' => 'BILL-A-001',
        ]);
        PayablePayment::query()->create([
            'payable_id' => $payableA->id,
            'payment_date' => '2026-05-20',
            'amount' => 40000,
            'cash_account_id' => $cashAccount->id,
            'note' => 'Bayar bahan baku',
            'journal_entry_id' => $supplierPaymentJournal->id,
            'paid_by' => $user->id,
        ]);

        PosSale::query()->create([
            'number' => 'POS-B-001',
            'sales_channel' => 'retail',
            'payment_method_id' => $paymentMethod->id,
            'gross_total' => 999999,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 999999,
            'cash_paid' => 999999,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => '2026-05-20 11:00:00',
            'sold_by' => $cashierB->id,
        ]);
        JournalEntry::query()->create([
            'company_id' => $companyB->id,
            'entry_no' => 'JE-POS-B',
            'entry_date' => '2026-05-20',
            'description' => 'Penjualan POS B',
            'status' => DocumentStatus::Posted,
            'source_module' => 'pos_sale',
            'source_reference' => 'POS-B-001',
        ]);

        $request = Request::create('/laporan/cashflow', 'GET', [
            'company_id' => $companyA->id,
            'date_from' => '2026-05-20',
            'date_to' => '2026-05-20',
        ]);
        $request->setUserResolver(fn () => $user);

        $report = app(CashflowReportService::class)->build($request);
        $rows = collect($report['transactions']->items());

        $this->assertSame(300000.0, (float) $report['summary']['total_in']);
        $this->assertSame(90000.0, (float) $report['summary']['total_out']);
        $this->assertSame(210000.0, (float) $report['summary']['net_cashflow']);
        $this->assertSame(4, $report['summary']['transaction_count']);
        $this->assertSame(2, $report['summary']['cash_in_count']);
        $this->assertSame(2, $report['summary']['cash_out_count']);
        $this->assertSame((string) $companyA->id, (string) $report['filters']['company_id']);
        $this->assertTrue($rows->contains(fn (array $row) => $row['source'] === 'POS' && (float) $row['amount'] === 200000.0));
        $this->assertTrue($rows->contains(fn (array $row) => $row['source'] === 'Pembayaran Supplier' && (float) $row['amount'] === 40000.0));
        $this->assertFalse($rows->contains(fn (array $row) => ($row['note'] ?? null) && str_contains((string) $row['note'], 'POS-B-001')));
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
