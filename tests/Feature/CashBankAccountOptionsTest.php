<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class CashBankAccountOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_bank_options_only_include_flagged_active_asset_accounts(): void
    {
        $kas = Account::query()->create([
            'code' => '1001',
            'name' => 'Kas',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        Account::query()->create([
            'code' => '1002',
            'name' => 'Bank BCA',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        Account::query()->create([
            'code' => '1101',
            'name' => 'Piutang Usaha',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => false,
        ]);

        $options = Account::cashBankOptions();

        $this->assertCount(2, $options);
        $this->assertTrue($options->contains('id', $kas->id));
        $this->assertSame(['1001', '1002'], $options->pluck('code')->sort()->values()->all());
    }

    public function test_payments_page_lists_all_flagged_cash_bank_accounts(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);

        foreach (['1001' => 'Kas', '1002' => 'Bank BCA', '1003' => 'Bank Mandiri'] as $code => $name) {
            Account::query()->create([
                'code' => $code,
                'name' => $name,
                'type' => 'asset',
                'normal_balance' => 'debit',
                'is_active' => true,
                'is_cash_bank' => true,
            ]);
        }

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.payments'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Payments')
                ->has('cashAccounts', 3));
    }
}
