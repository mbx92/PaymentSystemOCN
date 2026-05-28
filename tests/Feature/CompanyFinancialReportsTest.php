<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Core\Models\Company;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class CompanyFinancialReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_revenue_report_groups_revenue_by_company_for_selected_year(): void
    {
        $this->disableErpMiddleware();

        $companyA = Company::query()->create([
            'name' => 'OC Networks',
            'legal_name' => 'PT OC Networks',
            'is_active' => true,
        ]);
        $companyB = Company::query()->create([
            'name' => 'Numa Packaging',
            'legal_name' => 'PT Numa Packaging',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['company_id' => $companyA->id]);

        $cashAccount = $this->createAccount('1101', 'Kas', 'asset', 'debit');
        $revenueAccount = $this->createAccount('4101', 'Pendapatan Jasa', 'revenue', 'credit');

        $this->createRevenueJournal($companyA->id, '2026-03-10', 'JE-REV-OC-2026', 'project_invoice_payment', 'INV-OC-2026', $cashAccount->id, $revenueAccount->id, 1000000);
        $this->createRevenueJournal($companyB->id, '2026-03-12', 'JE-REV-NUMA-2026', 'pos_sale', 'POS-NUMA-2026', $cashAccount->id, $revenueAccount->id, 2000000);
        $this->createRevenueJournal($companyA->id, '2025-12-31', 'JE-REV-OC-2025', 'project_invoice_payment', 'INV-OC-2025', $cashAccount->id, $revenueAccount->id, 9000000);

        $this->actingAs($user)
            ->get(route('reports.company-revenue', ['company_id' => 'all', 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Reports/CompanyRevenue')
                ->where('selected_year', 2026)
                ->where('totals.revenue', fn ($value) => (float) $value === 3000000.0)
                ->where('totals.company_count', 2)
                ->where('totals.entry_count', 2)
                ->has('rows', 2)
                ->where('rows', fn ($rows) => collect($rows)->contains(fn ($row) => $row['company_name'] === 'OC Networks' && (float) $row['revenue_total'] === 1000000.0))
                ->where('rows', fn ($rows) => collect($rows)->contains(fn ($row) => $row['company_name'] === 'Numa Packaging' && (float) $row['revenue_total'] === 2000000.0))
                ->where('source_pivot', fn ($rows) => collect($rows)->contains(fn ($row) => $row['company_name'] === 'OC Networks' && $row['source_label'] === 'Project Invoice Payment' && (float) $row['revenue_total'] === 1000000.0))
                ->where('source_pivot', fn ($rows) => collect($rows)->contains(fn ($row) => $row['company_name'] === 'Numa Packaging' && $row['source_label'] === 'POS' && (float) $row['revenue_total'] === 2000000.0))
            );
    }

    public function test_company_profit_loss_report_summarizes_revenue_and_expense_by_company(): void
    {
        $this->disableErpMiddleware();

        $companyA = Company::query()->create([
            'name' => 'OC Networks',
            'legal_name' => 'PT OC Networks',
            'is_active' => true,
        ]);
        $companyB = Company::query()->create([
            'name' => 'Numa Packaging',
            'legal_name' => 'PT Numa Packaging',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['company_id' => $companyA->id]);

        $cashAccount = $this->createAccount('1101', 'Kas', 'asset', 'debit');
        $revenueAccount = $this->createAccount('4101', 'Pendapatan Jasa', 'revenue', 'credit');
        $expenseAccount = $this->createAccount('5101', 'Beban Operasional', 'expense', 'debit');

        $this->createRevenueJournal($companyA->id, '2026-04-01', 'JE-REV-OC', 'project_invoice_payment', 'INV-OC', $cashAccount->id, $revenueAccount->id, 5000000);
        $this->createRevenueJournal($companyB->id, '2026-04-02', 'JE-REV-NUMA', 'pos_sale', 'POS-NUMA', $cashAccount->id, $revenueAccount->id, 3000000);
        $this->createExpenseJournal($companyA->id, '2026-04-03', 'JE-EXP-OC', 'cash_out', 'CO-OC', $cashAccount->id, $expenseAccount->id, 1200000);
        $this->createExpenseJournal($companyB->id, '2026-04-04', 'JE-EXP-NUMA', 'cash_out', 'CO-NUMA', $cashAccount->id, $expenseAccount->id, 4000000);
        $this->createExpenseJournal($companyA->id, '2025-12-30', 'JE-EXP-OLD', 'cash_out', 'CO-OLD', $cashAccount->id, $expenseAccount->id, 9900000);

        $this->actingAs($user)
            ->get(route('reports.company-profit-loss', ['company_id' => 'all', 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Reports/CompanyProfitLoss')
                ->where('selected_year', 2026)
                ->where('totals.revenue', fn ($value) => (float) $value === 8000000.0)
                ->where('totals.expense', fn ($value) => (float) $value === 5200000.0)
                ->where('totals.net_profit', fn ($value) => (float) $value === 2800000.0)
                ->where('totals.company_count', 2)
                ->has('rows', 2)
                ->where('rows', fn ($rows) => collect($rows)->contains(fn ($row) => $row['company_name'] === 'OC Networks'
                    && (float) $row['revenue_total'] === 5000000.0
                    && (float) $row['expense_total'] === 1200000.0
                    && (float) $row['net_profit'] === 3800000.0))
                ->where('rows', fn ($rows) => collect($rows)->contains(fn ($row) => $row['company_name'] === 'Numa Packaging'
                    && (float) $row['revenue_total'] === 3000000.0
                    && (float) $row['expense_total'] === 4000000.0
                    && (float) $row['net_profit'] === -1000000.0))
                ->where('type_pivot', fn ($rows) => collect($rows)->contains(fn ($row) => $row['company_name'] === 'OC Networks' && $row['account_type'] === 'revenue' && (float) $row['amount'] === 5000000.0))
                ->where('type_pivot', fn ($rows) => collect($rows)->contains(fn ($row) => $row['company_name'] === 'Numa Packaging' && $row['account_type'] === 'expense' && (float) $row['amount'] === 4000000.0))
            );
    }

    private function createAccount(string $code, string $name, string $type, string $normalBalance): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_active' => true,
            'is_cash_bank' => $type === 'asset',
        ]);
    }

    private function createRevenueJournal(
        int $companyId,
        string $entryDate,
        string $entryNo,
        string $sourceModule,
        string $sourceReference,
        int $cashAccountId,
        int $revenueAccountId,
        float $amount,
    ): void {
        $entry = JournalEntry::query()->create([
            'company_id' => $companyId,
            'entry_no' => $entryNo,
            'entry_date' => $entryDate,
            'description' => 'Revenue test '.$entryNo,
            'status' => DocumentStatus::Posted,
            'source_module' => $sourceModule,
            'source_reference' => $sourceReference,
        ]);

        $entry->lines()->createMany([
            [
                'account_id' => $cashAccountId,
                'description' => 'Kas masuk',
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'account_id' => $revenueAccountId,
                'description' => 'Pendapatan',
                'debit' => 0,
                'credit' => $amount,
            ],
        ]);
    }

    private function createExpenseJournal(
        int $companyId,
        string $entryDate,
        string $entryNo,
        string $sourceModule,
        string $sourceReference,
        int $cashAccountId,
        int $expenseAccountId,
        float $amount,
    ): void {
        $entry = JournalEntry::query()->create([
            'company_id' => $companyId,
            'entry_no' => $entryNo,
            'entry_date' => $entryDate,
            'description' => 'Expense test '.$entryNo,
            'status' => DocumentStatus::Posted,
            'source_module' => $sourceModule,
            'source_reference' => $sourceReference,
        ]);

        $entry->lines()->createMany([
            [
                'account_id' => $expenseAccountId,
                'description' => 'Beban',
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'account_id' => $cashAccountId,
                'description' => 'Kas keluar',
                'debit' => 0,
                'credit' => $amount,
            ],
        ]);
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleOrPermissionMiddleware::class,
            RoleMiddleware::class,
        ]);
    }
}
