<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\CashCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class ProjectCashAccountingCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_client_fund_cash_in_category_is_retired_globally(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $cashAccount = $this->cashAccount();

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
            ->assertSessionHasErrors('category')
            ->assertRedirect();

        $this->assertDatabaseMissing('cash_in', [
            'project_id' => $project->id,
            'category' => 'dana_material_client',
        ]);

        $this->assertFalse((bool) CashCategory::query()
            ->where('domain', 'cash_in')
            ->where('key', 'dana_material_client')
            ->value('is_active'));
    }

    public function test_material_client_fund_usage_category_is_retired_and_project_summary_is_removed(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject();
        $cashAccount = $this->cashAccount();

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
            ->assertSessionHasErrors('category')
            ->assertRedirect();

        $this->assertDatabaseMissing('cash_out', [
            'project_id' => $project->id,
            'category' => 'pemakaian_dana_material_client',
        ]);

        $this
            ->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->missing('project.summary.material_fund_received')
                ->missing('project.summary.material_fund_used')
                ->missing('project.summary.material_fund_balance')
                ->missing('cash_category_options.in')
                ->missing('cash_category_options.labels.dana_material_client')
                ->missing('cash_category_options.labels.pemakaian_dana_material_client')
                ->etc());

        $this->assertFalse((bool) CashCategory::query()
            ->where('domain', 'cash_out')
            ->where('key', 'pemakaian_dana_material_client')
            ->value('is_active'));
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
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
