<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Core\Models\Company;
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
