<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectCashAccountingCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_client_fund_posts_to_liability_account(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $cashAccount = $this->cashAccount();
        $liabilityAccount = Account::query()->where('code', '2006')->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->post(route('cash-in.store'), [
                'project_id' => $project->id,
                'cash_account_id' => $cashAccount->id,
                'category' => 'dana_material_client',
                'amount' => 750000,
                'date' => '2026-05-13',
                'note' => 'Dana material dari client',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $cashIn = CashIn::query()->where('project_id', $project->id)->firstOrFail();

        $this->assertDatabaseHas('journal_entries', [
            'id' => $cashIn->journal_entry_id,
            'source_module' => 'cash_in',
            'source_reference' => $cashIn->id,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $cashIn->journal_entry_id,
            'account_id' => $cashAccount->id,
            'debit' => '750000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $cashIn->journal_entry_id,
            'account_id' => $liabilityAccount->id,
            'debit' => '0.00',
            'credit' => '750000.00',
        ]);
    }

    public function test_material_client_fund_usage_posts_against_liability_and_updates_project_summary(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $cashAccount = $this->cashAccount();
        $liabilityAccount = Account::query()->where('code', '2006')->firstOrFail();

        $this
            ->actingAs($user)
            ->post(route('cash-in.store'), [
                'project_id' => $project->id,
                'cash_account_id' => $cashAccount->id,
                'category' => 'dana_material_client',
                'amount' => 1000000,
                'date' => '2026-05-13',
            ])
            ->assertSessionHasNoErrors();

        $response = $this
            ->actingAs($user)
            ->post(route('cash-out.store'), [
                'project_id' => $project->id,
                'cash_account_id' => $cashAccount->id,
                'category' => 'pemakaian_dana_material_client',
                'amount' => 350000,
                'date' => '2026-05-13',
                'recipient_name' => 'Supplier',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $cashOut = CashOut::query()->where('project_id', $project->id)->firstOrFail();

        $this->assertDatabaseHas('journal_entries', [
            'id' => $cashOut->journal_entry_id,
            'source_module' => 'cash_out',
            'source_reference' => $cashOut->id,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $cashOut->journal_entry_id,
            'account_id' => $liabilityAccount->id,
            'debit' => '350000.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $cashOut->journal_entry_id,
            'account_id' => $cashAccount->id,
            'debit' => '0.00',
            'credit' => '350000.00',
        ]);

        $this
            ->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('project.summary.material_fund_received', 1000000)
                ->where('project.summary.material_fund_used', 350000)
                ->where('project.summary.material_fund_balance', 650000)
                ->where('cash_category_options.labels.dana_material_client', 'Dana Material dari Client')
                ->where('cash_category_options.labels.pemakaian_dana_material_client', 'Pemakaian Dana Material Client')
                ->etc());
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ErpMaintenanceMode::class,
            \App\Http\Middleware\LogErpActivity::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }

    private function cashAccount(): Account
    {
        return Account::query()->firstOrCreate(
            ['code' => '1001'],
            [
                'name' => 'Kas',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'is_active' => true,
            ]
        );
    }

    private function createProject(): Project
    {
        $project = new Project([
            'name' => 'Project Material',
            'client_name' => 'Client',
            'total_value' => 1000000,
            'status' => 'berjalan',
        ]);
        $project->id = (string) Str::uuid();
        $project->save();

        return $project;
    }
}
