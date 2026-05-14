<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Purchasing\Models\Vendor;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierPaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_payments_exposes_supplier_payables(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $payable = $this->createPayable($vendor, 150000, 50000);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.payments'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Payments')
                ->where('payables.0.id', $payable->id)
                ->where('payables.0.vendor_name', 'Supplier Test')
                ->where('payables.0.outstanding_amount', 100000)
                ->where('summary.outstanding_total', 100000));
    }

    public function test_supplier_payment_debits_payable_and_credits_cash(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = $this->createAccount('1001', 'Kas', 'asset');
        $payableAccount = $this->createAccount('2001', 'Hutang Usaha', 'liability');
        $vendor = $this->createVendor();
        $payable = $this->createPayable($vendor, 150000, 0);

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.payments.supplier.store', $payable), [
                'payment_date' => '2026-05-14',
                'amount' => 60000,
                'cash_account_id' => $cash->id,
                'note' => 'Transfer termin supplier',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payable->refresh();
        $payment = PayablePayment::query()->firstOrFail();
        $entry = JournalEntry::query()->with('lines')->findOrFail($payment->journal_entry_id);

        $this->assertSame(60000.0, (float) $payable->paid_amount);
        $this->assertSame(DocumentStatus::PartiallyPaid, $payable->status);
        $this->assertDatabaseHas('payable_payments', [
            'payable_id' => $payable->id,
            'cash_account_id' => $cash->id,
            'amount' => '60000.00',
            'note' => 'Transfer termin supplier',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $payableAccount->id,
            'debit' => '60000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => '0.00',
            'credit' => '60000.00',
        ]);
    }

    public function test_supplier_payment_marks_payable_paid_when_fully_settled(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = $this->createAccount('1001', 'Kas', 'asset');
        $this->createAccount('2001', 'Hutang Usaha', 'liability');
        $payable = $this->createPayable($this->createVendor(), 100000, 25000);

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.payments.supplier.store', $payable), [
                'payment_date' => '2026-05-14',
                'amount' => 75000,
                'cash_account_id' => $cash->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payable->refresh();

        $this->assertSame(100000.0, (float) $payable->paid_amount);
        $this->assertSame(DocumentStatus::Paid, $payable->status);
    }

    public function test_supplier_payment_cannot_exceed_outstanding_amount(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = $this->createAccount('1001', 'Kas', 'asset');
        $this->createAccount('2001', 'Hutang Usaha', 'liability');
        $payable = $this->createPayable($this->createVendor(), 100000, 25000);

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.payments.supplier.store', $payable), [
                'payment_date' => '2026-05-14',
                'amount' => 80000,
                'cash_account_id' => $cash->id,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payable_payments', 0);
    }

    private function createAccount(string $code, string $name, string $type): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $type === 'asset' ? 'debit' : 'credit',
            'is_active' => true,
        ]);
    }

    private function createVendor(): Vendor
    {
        return Vendor::query()->create([
            'code' => 'SUP-TEST',
            'name' => 'Supplier Test',
            'lead_time_days' => 7,
            'is_active' => true,
        ]);
    }

    private function createPayable(Vendor $vendor, float $amount, float $paidAmount): Payable
    {
        return Payable::query()->create([
            'vendor_id' => $vendor->id,
            'bill_no' => 'BILL-TEST',
            'bill_date' => '2026-05-14',
            'due_date' => '2026-05-28',
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'status' => $paidAmount > 0 ? DocumentStatus::PartiallyPaid : DocumentStatus::Posted,
        ]);
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ErpMaintenanceMode::class,
            \App\Http\Middleware\LogErpActivity::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }
}
