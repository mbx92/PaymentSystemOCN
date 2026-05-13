<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashflowAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_menu_permission_can_open_cashflow_page(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
        ]);

        Permission::firstOrCreate(['name' => 'menu.erp.accounting', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('menu.erp.accounting');

        Account::query()->create([
            'code' => '1001',
            'name' => 'Kas',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.accounting.cashflow'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Cashflow')
                ->has('entries')
                ->has('totals')
                ->where('canMutate', false));
    }
}
