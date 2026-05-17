<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\CoaSetting;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Core\Models\Company;
use App\Models\CashIn;
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

    public function test_accounting_utilities_can_backfill_cash_account_ids_from_journal(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $cash = Account::query()->updateOrCreate(['code' => '1101'], [
            'name' => 'Kas Utama',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        $revenue = Account::query()->updateOrCreate(['code' => '4003'], [
            'name' => 'Pendapatan Project',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);

        $entry = JournalEntry::query()->create([
            'entry_no' => 'JE-INV-001',
            'entry_date' => '2026-05-14',
            'description' => 'Pembayaran invoice',
            'status' => 'posted',
            'source_module' => 'project_invoice_payment',
            'source_reference' => 'pay-1',
        ]);
        $entry->lines()->createMany([
            ['account_id' => $cash->id, 'debit' => 500000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500000],
        ]);

        $cashIn = CashIn::query()->create([
            'amount' => 500000,
            'date' => '2026-05-14',
            'category' => 'project_payment',
            'journal_entry_id' => $entry->id,
            'cash_account_id' => null,
            'created_by' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.utilities'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Utilities')
                ->where('cashAccountBackfill.cash_in_ready', 1)
                ->where('cashAccountBackfill.cash_in_pending', 1)
                ->has('cashAccountBackfill.samples', 1)
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.backfill-cash-accounts'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('cash_in', [
            'id' => $cashIn->id,
            'cash_account_id' => $cash->id,
        ]);
    }

    public function test_accounting_utilities_can_correct_pos_channel_payable_from_latest_coa_setting(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $company = Company::query()->create(['name' => 'Usaha 1', 'is_active' => true]);
        $cash = Account::query()->updateOrCreate(['code' => '1001'], [
            'code' => '1001',
            'name' => 'Kas',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        $revenue = Account::query()->updateOrCreate(['code' => '4002'], [
            'code' => '4002',
            'name' => 'Pendapatan Penjualan POS',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);
        $expense = Account::query()->updateOrCreate(['code' => '5014'], [
            'code' => '5014',
            'name' => 'Beban Marketing & Iklan',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        $payable = Account::query()->updateOrCreate(['code' => '2007'], [
            'code' => '2007',
            'name' => 'Hutang Estimasi Biaya Channel',
            'type' => 'liability',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);

        CoaSetting::query()->create(['key' => 'pos_sale_sales_channel_admin_expense', 'account_id' => $expense->id]);
        CoaSetting::query()->create(['key' => 'pos_sale_sales_channel_admin_payable', 'account_id' => $payable->id]);

        $entry = JournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_no' => 'JE-POS-001',
            'entry_date' => '2026-05-14',
            'description' => 'Penjualan POS TRX-001',
            'status' => 'posted',
            'source_module' => 'pos_sale',
            'source_reference' => 'TRX-001',
        ]);
        $entry->lines()->createMany([
            ['account_id' => $cash->id, 'debit' => 60000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 60000],
            ['account_id' => $expense->id, 'debit' => 6616, 'credit' => 0],
            ['account_id' => $expense->id, 'debit' => 0, 'credit' => 6616],
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.utilities', ['q' => 'JE-POS-001']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Utilities')
                ->where('posChannelCorrection.can_correct', true)
                ->where('posChannelCorrection.candidate_count', 1)
                ->where('posChannelCorrection.payable_account', '2007 - Hutang Estimasi Biaya Channel')
                ->where('posChannelCorrection.candidates.0.entry_no', 'JE-POS-001')
                ->where('posChannelCorrection.candidates.0.candidate_amount', 6616)
                ->etc());

        $this
            ->actingAs($user)
            ->post(route('erp.accounting.utilities.correct-pos-channel-payable'), [
                'journal_entry_ids' => [$entry->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $expense->id,
            'debit' => '6616.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $payable->id,
            'debit' => '0.00',
            'credit' => '6616.00',
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
