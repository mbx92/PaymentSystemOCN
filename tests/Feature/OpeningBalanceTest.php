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

class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_balance_page_exposes_active_accounts_and_history(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = $this->createAccount('1001', 'Kas', 'asset');
        $this->createAccount('9999', 'Akun Nonaktif', 'asset', false);
        $capital = $this->createAccount('3001', 'Modal Pemilik', 'equity');
        $companyId = (int) Company::query()->value('id');

        $entry = JournalEntry::query()->create([
            'company_id' => $companyId,
            'entry_no' => 'JE-OPENING',
            'entry_date' => '2026-05-01',
            'description' => 'Saldo awal Mei',
            'status' => DocumentStatus::Posted,
            'source_module' => 'opening_balance',
            'source_reference' => 'OPENING-20260501-000001',
        ]);
        $entry->lines()->createMany([
            ['account_id' => $cash->id, 'debit' => 500000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 500000],
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.opening-balance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/OpeningBalance')
                ->where('accounts', fn ($accounts) => collect($accounts)->contains(fn ($account) => $account['code'] === '1001'))
                ->where('accounts', fn ($accounts) => collect($accounts)->contains(fn ($account) => $account['code'] === '3001'))
                ->where('accounts', fn ($accounts) => ! collect($accounts)->contains(fn ($account) => $account['code'] === '9999'))
                ->where('openingEntries.data.0.entry_no', 'JE-OPENING')
                ->where('openingEntries.data.0.total_debit', 500000));
    }

    public function test_opening_balance_posts_balanced_journal_to_gl(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = $this->createAccount('1001', 'Kas', 'asset');
        $equipment = $this->createAccount('1201', 'Peralatan', 'asset');
        $capital = $this->createAccount('3001', 'Modal Pemilik', 'equity');
        $companyId = (int) Company::query()->value('id');

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.opening-balance.store'), [
                'company_id' => $companyId,
                'entry_date' => '2026-05-01',
                'description' => 'Saldo awal periode Mei',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 150000, 'credit' => 0],
                    ['account_id' => $equipment->id, 'debit' => 350000, 'credit' => 0],
                    ['account_id' => $capital->id, 'debit' => 0, 'credit' => 500000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $entry = JournalEntry::query()->with('lines')->where('source_module', 'opening_balance')->firstOrFail();

        $this->assertSame('Saldo awal periode Mei', $entry->description);
        $this->assertSame('2026-05-01', $entry->entry_date->toDateString());
        $this->assertSame(DocumentStatus::Posted, $entry->status);
        $this->assertSame($user->id, $entry->posted_by);
        $this->assertSame($companyId, (int) $entry->company_id);
        $this->assertStringStartsWith('OPENING-20260501-', (string) $entry->source_reference);
        $this->assertSame(500000.0, (float) $entry->lines->sum('debit'));
        $this->assertSame(500000.0, (float) $entry->lines->sum('credit'));

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => '150000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $capital->id,
            'debit' => '0.00',
            'credit' => '500000.00',
        ]);
    }

    public function test_opening_balance_rejects_unbalanced_lines(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = $this->createAccount('1001', 'Kas', 'asset');
        $capital = $this->createAccount('3001', 'Modal Pemilik', 'equity');

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.opening-balance.store'), [
                'company_id' => (int) Company::query()->value('id'),
                'entry_date' => '2026-05-01',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 150000, 'credit' => 0],
                    ['account_id' => $capital->id, 'debit' => 0, 'credit' => 100000],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
    }

    public function test_opening_balance_rejects_inactive_account(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = $this->createAccount('1001', 'Kas', 'asset');
        $inactive = $this->createAccount('3001', 'Modal Lama', 'equity', false);

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.opening-balance.store'), [
                'company_id' => (int) Company::query()->value('id'),
                'entry_date' => '2026-05-01',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 150000, 'credit' => 0],
                    ['account_id' => $inactive->id, 'debit' => 0, 'credit' => 150000],
                ],
            ])
            ->assertSessionHasErrors('lines.1.account_id');

        $this->assertDatabaseCount('journal_entries', 0);
    }

    private function createAccount(string $code, string $name, string $type, bool $isActive = true): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $type === 'asset' ? 'debit' : 'credit',
            'is_active' => $isActive,
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
