<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Core\Models\Company;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\CashBankTransfer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class CashBankTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_bank_transfer_posts_journal_between_cash_accounts(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $bank = $this->cashAccount('1002', 'Bank BCA');
        $petty = $this->cashAccount('1004', 'Kas Kecil (Petty Cash)');
        $project = Project::query()->create([
            'name' => 'Project Lapangan',
            'client_name' => 'Client',
            'total_value' => 1000000,
            'status' => 'berjalan',
        ]);

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.cash-bank-transfer.store'), [
                'from_account_id' => $bank->id,
                'to_account_id' => $petty->id,
                'amount' => 2500000,
                'transfer_date' => '2026-05-17',
                'project_id' => $project->id,
                'note' => 'Tarik tunai untuk operasional project',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $transfer = CashBankTransfer::query()->firstOrFail();
        $this->assertSame($bank->id, (int) $transfer->from_account_id);
        $this->assertSame($petty->id, (int) $transfer->to_account_id);
        $this->assertSame($project->id, $transfer->project_id);

        $entry = JournalEntry::query()->where('source_module', 'cash_bank_transfer')->firstOrFail();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $petty->id,
            'debit' => '2500000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $bank->id,
            'debit' => '0.00',
            'credit' => '2500000.00',
        ]);

        $revenueLines = JournalLine::query()
            ->where('journal_entry_id', $entry->id)
            ->whereHas('account', fn ($q) => $q->whereIn('type', ['revenue', 'expense']))
            ->count();
        $this->assertSame(0, $revenueLines);
    }

    public function test_cash_bank_transfer_index_filters_by_company(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $companyA = Company::query()->create(['name' => 'Usaha A', 'is_active' => true]);
        $companyB = Company::query()->create(['name' => 'Usaha B', 'is_active' => true]);
        $bank = $this->cashAccount('1002', 'Bank BCA');
        $petty = $this->cashAccount('1004', 'Kas Kecil');

        $transferA = CashBankTransfer::query()->create([
            'from_account_id' => $bank->id,
            'to_account_id' => $petty->id,
            'amount' => 100000,
            'transfer_date' => '2026-05-17',
            'created_by' => $user->id,
        ]);
        $entryA = JournalEntry::query()->create([
            'company_id' => $companyA->id,
            'entry_no' => 'JE-MUT-A',
            'entry_date' => '2026-05-17',
            'description' => 'Mutasi A',
            'status' => 'posted',
            'source_module' => 'cash_bank_transfer',
            'source_reference' => (string) $transferA->id,
        ]);
        $transferA->update(['journal_entry_id' => $entryA->id]);

        $transferB = CashBankTransfer::query()->create([
            'from_account_id' => $bank->id,
            'to_account_id' => $petty->id,
            'amount' => 200000,
            'transfer_date' => '2026-05-17',
            'created_by' => $user->id,
        ]);
        $entryB = JournalEntry::query()->create([
            'company_id' => $companyB->id,
            'entry_no' => 'JE-MUT-B',
            'entry_date' => '2026-05-17',
            'description' => 'Mutasi B',
            'status' => 'posted',
            'source_module' => 'cash_bank_transfer',
            'source_reference' => (string) $transferB->id,
        ]);
        $transferB->update(['journal_entry_id' => $entryB->id]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.cash-bank-transfer', ['company_id' => $companyA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/CashBankTransfer')
                ->where('total', 100000)
                ->has('transfers.data', 1)
                ->where('transfers.data.0.id', $transferA->id));
    }

    public function test_cash_bank_transfer_index_page_loads(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $this->cashAccount('1001', 'Kas');
        $this->cashAccount('1002', 'Bank BCA');

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.cash-bank-transfer'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/CashBankTransfer')
                ->has('cashAccounts', 2));
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

    private function cashAccount(string $code, string $name): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
    }
}
