<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\CashCategory;
use App\Models\CategoryCoaMapping;
use App\Models\Project;
use App\Models\TeamDistribution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MemberPaymentCashflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_payment_posts_cash_out_and_appears_in_cashflow(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);

        Permission::firstOrCreate(['name' => 'menu.erp.accounting', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('menu.erp.accounting');
        $member = User::factory()->create(['name' => 'Budi Anggota']);

        $expenseAccount = Account::query()->firstOrCreate(
            ['code' => '5002'],
            ['name' => 'Gaji', 'type' => 'expense', 'normal_balance' => 'debit', 'is_active' => true]
        );
        $cashAccount = Account::query()->firstOrCreate(
            ['code' => '1001'],
            ['name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]
        );

        CashCategory::query()->firstOrCreate(
            ['domain' => 'cash_out', 'key' => 'biaya_tim'],
            ['label' => 'Biaya Tim', 'is_active' => true, 'sort_order' => 10]
        );

        CategoryCoaMapping::query()->firstOrCreate(
            ['domain' => 'cash_out', 'category' => 'biaya_tim'],
            ['account_id' => $expenseAccount->id]
        );

        $project = $this->createProject('Project Alpha');

        $distribution = TeamDistribution::query()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_in_project' => 'developer',
            'percentage' => 25,
            'base_pay' => 8_000_000,
            'bonus' => 500_000,
            'total_pay' => 8_500_000,
        ]);

        $this->actingAs($user)
            ->post(route('erp.accounting.payments.member.store', $distribution), [
                'payment_date' => '2026-05-16',
                'amount' => 8_500_000,
                'cash_account_id' => $cashAccount->id,
                'note' => 'Bayar honor project',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $distribution->refresh();
        $this->assertNotNull($distribution->cash_out_id);
        $this->assertNotNull($distribution->paid_at);

        $this->actingAs($user)
            ->get(route('erp.accounting.cashflow', ['source' => 'member_payment']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/Cashflow')
                ->has('entries', 1)
                ->where('entries.0.source', 'member_payment')
                ->where('entries.0.source_name', 'Pembayaran Anggota')
                ->where('entries.0.recipient_name', 'Budi Anggota')
                ->where('entries.0.category', 'biaya_tim'));

        $this->actingAs($user)
            ->post(route('erp.accounting.payments.member.store', $distribution), [
                'payment_date' => '2026-05-17',
                'amount' => 1,
                'cash_account_id' => $cashAccount->id,
            ])
            ->assertSessionHasErrors('amount');
    }

    private function createAccount(string $code, string $name, string $type): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $type === 'asset' ? 'debit' : 'credit',
            'is_active' => true,
        ]);
    }

    private function createProject(string $name): Project
    {
        $project = new Project([
            'name' => $name,
            'client_name' => 'Client',
            'total_value' => 10_000_000,
            'status' => 'berjalan',
        ]);
        $project->id = (string) Str::uuid();
        $project->save();

        return $project;
    }
}
