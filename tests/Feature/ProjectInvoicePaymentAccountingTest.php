<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\CoaSetting;
use App\ERP\Accounting\Models\JournalEntry;
use App\Models\CashIn;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetItem;
use App\Models\ProjectPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectInvoicePaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_invoice_payment_stores_cash_account_and_posts_asset_debit(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = $this->cashAccount('1002', 'Bank BCA');
        $revenue = $this->account('4003', 'Pendapatan Project', 'revenue', 'credit');

        CoaSetting::query()->create(['key' => 'project_invoice_cash_account', 'account_id' => $cash->id]);
        CoaSetting::query()->create(['key' => 'project_invoice_revenue_account', 'account_id' => $revenue->id]);

        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'transfer',
            'name' => 'Transfer',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'name' => 'Project Invoice Pay',
            'client_name' => 'Client',
            'total_value' => 2000000,
            'status' => 'selesai',
            'finished_at' => '2026-05-17',
        ]);

        $this
            ->actingAs($user)
            ->post(route('erp.sales.project-invoices.payments.store', $project), [
                'amount' => 500000,
                'date' => '2026-05-17',
                'payment_method_id' => $paymentMethod->id,
                'cash_account_id' => $cash->id,
                'note' => 'DP invoice',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cashIn = CashIn::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame($cash->id, (int) $cashIn->cash_account_id);

        $entry = JournalEntry::query()->with('lines')->findOrFail($cashIn->journal_entry_id);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => '500000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $revenue->id,
            'debit' => '0.00',
            'credit' => '500000.00',
        ]);
    }

    public function test_project_invoice_payment_uses_budget_items_amount_when_project_total_value_is_zero(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = $this->cashAccount('1002', 'Bank BCA');
        $revenue = $this->account('4003', 'Pendapatan Project', 'revenue', 'credit');

        CoaSetting::query()->create(['key' => 'project_invoice_cash_account', 'account_id' => $cash->id]);
        CoaSetting::query()->create(['key' => 'project_invoice_revenue_account', 'account_id' => $revenue->id]);

        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'transfer',
            'name' => 'Transfer',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'name' => 'Project Budget Based Invoice',
            'client_name' => 'Client',
            'total_value' => 0,
            'status' => 'selesai',
            'finished_at' => '2026-05-17',
        ]);

        $budget = ProjectBudget::query()->create([
            'name' => 'Budget Project Budget Based Invoice',
            'client_name' => 'Client',
            'status' => 'deal',
            'converted_project_id' => $project->id,
        ]);

        ProjectBudgetItem::query()->create([
            'project_budget_id' => $budget->id,
            'item_type' => 'service',
            'name' => 'Implementasi Sistem',
            'uom' => 'paket',
            'qty' => 1,
            'unit_price' => 1500000,
            'sort_order' => 1,
        ]);

        $this
            ->actingAs($user)
            ->post(route('erp.sales.project-invoices.payments.store', $project), [
                'amount' => 500000,
                'date' => '2026-05-17',
                'payment_method_id' => $paymentMethod->id,
                'cash_account_id' => $cash->id,
                'note' => 'Pembayaran invoice tanpa termin eksplisit',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cash_in', [
            'project_id' => $project->id,
            'amount' => '500000.00',
        ]);
    }

    public function test_accounting_payments_page_lists_cash_bank_accounts(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $this->cashAccount('1001', 'Kas');

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.payments'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Payments')
                ->has('cashAccounts', 1)
                ->where('cashAccounts.0.code', '1001'));
    }

    public function test_project_timeline_marks_term_paid_when_invoice_payment_covers_it(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = Project::query()->create([
            'name' => 'Project Timeline',
            'client_name' => 'Client',
            'total_value' => 1000000,
            'status' => 'selesai',
            'finished_at' => '2026-05-17',
        ]);

        ProjectPayment::query()->create([
            'project_id' => $project->id,
            'term_number' => 1,
            'percentage' => 50,
            'amount' => 500000,
        ]);
        ProjectPayment::query()->create([
            'project_id' => $project->id,
            'term_number' => 2,
            'percentage' => 50,
            'amount' => 500000,
        ]);

        CashIn::query()->create([
            'project_id' => $project->id,
            'category' => 'pendapatan_project',
            'amount' => 1000000,
            'date' => '2026-05-18',
            'note' => 'Pelunasan invoice project',
            'created_by' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('project.payments.0.is_paid', true)
                ->where('project.payments.0.timeline_paid_at', '2026-05-18')
                ->where('project.payments.1.is_paid', true)
                ->where('project.payments.1.timeline_paid_at', '2026-05-18')
                ->etc());
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ErpMaintenanceMode::class,
            \App\Http\Middleware\LogErpActivity::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }

    private function cashAccount(string $code, string $name): Account
    {
        return $this->account($code, $name, 'asset', 'debit');
    }

    private function account(string $code, string $name, string $type, string $normalBalance): Account
    {
        return Account::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'normal_balance' => $normalBalance,
                'is_active' => true,
                'is_cash_bank' => $type === 'asset' && preg_match('/kas|bank|cash|giro/i', $name) === 1,
            ]
        );
    }
}
