<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Core\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserCompanyAccountingMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_company_is_exposed_in_user_management_and_used_for_new_journals(): void
    {
        $this->disableErpMiddleware();

        $companyA = Company::query()->create(['name' => 'Usaha 1', 'is_active' => true]);
        $companyB = Company::query()->create(['name' => 'Usaha 2', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $companyB->id]);
        $cash = Account::query()->create(['code' => '1001', 'name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]);
        $equity = Account::query()->create(['code' => '3001', 'name' => 'Modal', 'type' => 'equity', 'normal_balance' => 'credit', 'is_active' => true]);

        $this
            ->actingAs($user)
            ->get(route('users.accounts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->where('users.data.0.company_id', $companyB->id)
                ->where('users.data.0.company_name', 'Usaha 2')
                ->has('companies', 3)
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.opening-balance.store'), [
                'entry_date' => '2026-05-14',
                'description' => 'Saldo awal usaha user',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
                    ['account_id' => $equity->id, 'debit' => 0, 'credit' => 100000],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $entry = JournalEntry::query()->firstOrFail();
        $this->assertSame($companyB->id, $entry->company_id);
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
