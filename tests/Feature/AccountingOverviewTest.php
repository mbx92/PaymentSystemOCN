<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Core\Models\Company;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
