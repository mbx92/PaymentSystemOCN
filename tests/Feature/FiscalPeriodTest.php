<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Core\Models\Company;
use App\ERP\Core\Models\FiscalPeriod;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\CashCategory;
use App\Models\CashIn;
use App\Models\CategoryCoaMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class FiscalPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiscal_period_page_renders_and_can_close_and_reopen_month(): void
    {
        $this->disableErpMiddleware();

        $company = Company::query()->create([
            'name' => 'OCN Main',
            'legal_name' => 'PT OCN Main',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('erp.accounting.fiscal-periods', ['company_id' => $company->id, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Accounting/FiscalPeriods')
                ->where('selected_company_id', $company->id)
                ->where('selected_year', 2026)
                ->has('periods.monthly', 12)
            );

        $this->actingAs($user)
            ->post(route('erp.accounting.fiscal-periods.store'), [
                'company_id' => $company->id,
                'period_type' => 'monthly',
                'period_year' => 2026,
                'period_month' => 5,
                'notes' => 'Closing Mei selesai',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $period = FiscalPeriod::query()->where([
            'company_id' => $company->id,
            'period_type' => 'monthly',
            'period_year' => 2026,
            'period_month' => 5,
        ])->firstOrFail();

        $this->assertTrue($period->is_closed);
        $this->assertSame('Closing Mei selesai', $period->notes);

        $this->actingAs($user)
            ->post(route('erp.accounting.fiscal-periods.reopen', $period))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($period->fresh()->is_closed);
    }

    public function test_closed_period_blocks_cashflow_posting_only_for_closed_company(): void
    {
        $this->disableErpMiddleware();

        $companyA = Company::query()->create([
            'name' => 'OCN A',
            'legal_name' => 'PT OCN A',
            'is_active' => true,
        ]);
        $companyB = Company::query()->create([
            'name' => 'OCN B',
            'legal_name' => 'PT OCN B',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['company_id' => $companyA->id]);

        $cashAccount = Account::query()->create([
            'code' => '1001',
            'name' => 'Kas Utama',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_cash_bank' => true,
        ]);
        $equityAccount = Account::query()->create([
            'code' => '3001',
            'name' => 'Modal Pemilik',
            'type' => 'equity',
            'normal_balance' => 'credit',
            'is_active' => true,
            'is_cash_bank' => false,
        ]);
        CashCategory::query()->create([
            'domain' => 'cash_in',
            'key' => 'investasi_masuk',
            'label' => 'Investasi Masuk',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        CategoryCoaMapping::query()->create([
            'domain' => 'cash_in',
            'category' => 'investasi_masuk',
            'account_id' => $equityAccount->id,
        ]);

        FiscalPeriod::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Tutup buku Mei 2026',
            'period_type' => 'monthly',
            'period_year' => 2026,
            'period_month' => 5,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'is_closed' => true,
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);

        $payload = [
            'type' => 'in',
            'project_id' => '',
            'cash_account_id' => $cashAccount->id,
            'payment_method_id' => null,
            'category' => 'investasi_masuk',
            'amount' => 1000000,
            'date' => '2026-05-10',
            'note' => 'Setoran modal',
            'recipient_name' => '',
        ];

        $this->actingAs($user)
            ->post(route('erp.accounting.cashflow.store'), $payload + ['company_id' => $companyA->id])
            ->assertSessionHasErrors('date');

        $this->assertDatabaseCount('cash_in', 0);

        $this->actingAs($user)
            ->post(route('erp.accounting.cashflow.store'), $payload + ['company_id' => $companyB->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('cash_in', 1);
        $this->assertSame(1, CashIn::query()->count());
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
