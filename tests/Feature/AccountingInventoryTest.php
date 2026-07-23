<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\AccountingInventoryRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class AccountingInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_record_posts_debit_asset_and_credit_cash(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $peralatan = $this->assetAccount('1401', 'Peralatan');
        $bank = $this->cashAccount('1002', 'Bank BCA');

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.inventaris.store'), [
                'item_name' => 'Laptop Admin',
                'qty' => 2,
                'unit_price' => 7500000,
                'acquisition_date' => '2026-05-17',
                'asset_account_id' => $peralatan->id,
                'cash_account_id' => $bank->id,
                'note' => 'Pembelian perangkat kantor',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $record = AccountingInventoryRecord::query()->firstOrFail();
        $this->assertSame('Laptop Admin', $record->item_name);
        $this->assertSame(2.0, (float) $record->qty);
        $this->assertSame(7500000.0, (float) $record->unit_price);
        $this->assertSame(15000000.0, (float) $record->amount);
        $this->assertSame($peralatan->id, (int) $record->asset_account_id);
        $this->assertSame($bank->id, (int) $record->cash_account_id);

        $entry = JournalEntry::query()->where('source_module', 'accounting_inventory')->firstOrFail();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $peralatan->id,
            'debit' => '15000000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $bank->id,
            'debit' => '0.00',
            'credit' => '15000000.00',
        ]);

        $plLines = JournalLine::query()
            ->where('journal_entry_id', $entry->id)
            ->whereHas('account', fn ($q) => $q->whereIn('type', ['revenue', 'expense']))
            ->count();
        $this->assertSame(0, $plLines);
    }

    public function test_inventory_record_appears_in_cashflow_as_outflow(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $peralatan = $this->assetAccount('1401', 'Peralatan');
        $this->cashAccount('1001', 'Kas');
        $bank = $this->cashAccount('1002', 'Bank BCA');

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.inventaris.store'), [
                'item_name' => 'Printer Thermal',
                'qty' => 1,
                'unit_price' => 2500000,
                'acquisition_date' => '2026-05-17',
                'asset_account_id' => $peralatan->id,
                'cash_account_id' => $bank->id,
                'note' => 'Kantor pusat',
            ])
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.cashflow', ['source' => 'inventaris']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ERP/Accounting/Cashflow')
                ->where('totals.cash_out', 2500000)
                ->has('entries.data', 1)
                ->where('entries.data.0.type', 'out')
                ->where('entries.data.0.source', 'inventaris')
                ->where('entries.data.0.amount', 2500000)
                ->where('entries.data.0.recipient_name', 'Printer Thermal')
            );
    }

    public function test_inventaris_index_provides_default_peralatan_account(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $peralatan = $this->assetAccount('1401', 'Peralatan');
        $this->cashAccount('1002', 'Bank BCA');

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.inventaris'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Inventaris')
                ->where('defaultAssetAccountId', $peralatan->id)
                ->has('assetAccounts', 1)
            );
    }

    public function test_inventaris_index_supports_server_side_search_and_pagination(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $peralatan = $this->assetAccount('1401', 'Peralatan');
        $bank = $this->cashAccount('1002', 'Bank BCA');

        foreach (range(1, 26) as $index) {
            $this
                ->actingAs($user)
                ->post(route('erp.accounting.inventaris.store'), [
                    'item_name' => $index === 1 ? 'Laptop Admin' : "Inventaris #{$index}",
                    'qty' => 1,
                    'unit_price' => 1000000 + $index,
                    'acquisition_date' => '2026-05-17',
                    'asset_account_id' => $peralatan->id,
                    'cash_account_id' => $bank->id,
                ])
                ->assertSessionHasNoErrors();
        }

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.inventaris', ['q' => 'Laptop', 'per_page' => 25]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Inventaris')
                ->has('records.data', 1)
                ->where('records.data.0.item_name', 'Laptop Admin')
                ->where('records.total', 1)
                ->where('filters.q', 'Laptop')
                ->where('filters.per_page', 25)
            );

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.inventaris', ['per_page' => 10, 'page' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Inventaris')
                ->has('records.data', 10)
                ->where('records.per_page', 10)
                ->where('records.last_page', 3)
                ->where('records.total', 26)
                ->has('records.links')
            );

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.inventaris', ['per_page' => 10, 'page' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Inventaris')
                ->has('records.data', 6)
                ->where('records.current_page', 3)
            );
    }

    public function test_inventory_record_can_be_updated_with_reverse_and_repost(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $peralatan = $this->assetAccount('1401', 'Peralatan');
        $kendaraan = $this->assetAccount('1402', 'Kendaraan');
        $bank = $this->cashAccount('1002', 'Bank BCA');

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.inventaris.store'), [
                'item_name' => 'Laptop Admin',
                'qty' => 1,
                'unit_price' => 10000000,
                'acquisition_date' => '2026-05-17',
                'asset_account_id' => $peralatan->id,
                'cash_account_id' => $bank->id,
            ])
            ->assertSessionHasNoErrors();

        $record = AccountingInventoryRecord::query()->firstOrFail();
        $originalEntryId = $record->journal_entry_id;

        $this
            ->actingAs($user)
            ->patch(route('erp.accounting.inventaris.update', $record), [
                'item_name' => 'Laptop Admin Pro',
                'qty' => 2,
                'unit_price' => 6000000,
                'acquisition_date' => '2026-05-18',
                'asset_account_id' => $kendaraan->id,
                'cash_account_id' => $bank->id,
                'note' => 'Upgrade',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $record->refresh();
        $this->assertSame('Laptop Admin Pro', $record->item_name);
        $this->assertSame(2.0, (float) $record->qty);
        $this->assertSame(6000000.0, (float) $record->unit_price);
        $this->assertSame(12000000.0, (float) $record->amount);
        $this->assertSame($kendaraan->id, (int) $record->asset_account_id);
        $this->assertNotSame($originalEntryId, $record->journal_entry_id);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $originalEntryId,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'reversed_entry_id' => $originalEntryId,
            'source_module' => 'reversal',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $record->journal_entry_id,
            'account_id' => $kendaraan->id,
            'debit' => '12000000.00',
            'credit' => '0.00',
        ]);
    }

    public function test_inventory_record_can_be_deleted_with_reversal(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $peralatan = $this->assetAccount('1401', 'Peralatan');
        $bank = $this->cashAccount('1002', 'Bank BCA');

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.inventaris.store'), [
                'item_name' => 'Printer Thermal',
                'qty' => 1,
                'unit_price' => 2500000,
                'acquisition_date' => '2026-05-17',
                'asset_account_id' => $peralatan->id,
                'cash_account_id' => $bank->id,
            ])
            ->assertSessionHasNoErrors();

        $record = AccountingInventoryRecord::query()->firstOrFail();
        $originalEntryId = $record->journal_entry_id;

        $this
            ->actingAs($user)
            ->delete(route('erp.accounting.inventaris.destroy', $record))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseMissing('accounting_inventory_records', ['id' => $record->id]);
        $this->assertDatabaseHas('journal_entries', [
            'reversed_entry_id' => $originalEntryId,
            'source_module' => 'reversal',
        ]);
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

    private function assetAccount(string $code, string $name): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => false,
        ]);
    }
}
