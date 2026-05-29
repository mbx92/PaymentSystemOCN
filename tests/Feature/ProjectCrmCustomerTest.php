<?php

namespace Tests\Feature;

use App\ERP\CRM\Models\CrmCustomer;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class ProjectCrmCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_create_uses_crm_customer_as_client_snapshot(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $customer = CrmCustomer::query()->create([
            'code' => 'CUST-9001',
            'name' => 'Budi Santoso',
            'company' => 'PT Kamera Rapi',
            'email' => 'finance@kamera.test',
            'phone' => '08123456789',
            'source' => 'manual',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Instalasi CCTV Gudang',
                'crm_customer_id' => $customer->id,
                'client_name' => 'Nama Manual Harus Diabaikan',
                'client_contact' => 'Kontak Manual Harus Diabaikan',
                'project_type' => 'cctv_installation',
                'total_value' => 15000000,
                'status' => 'negosiasi',
                'payment_scheme' => 'terms',
                'payments' => [
                    ['percentage' => 50, 'note' => 'DP'],
                    ['percentage' => 50, 'note' => 'Pelunasan'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'name' => 'Instalasi CCTV Gudang',
            'crm_customer_id' => $customer->id,
            'client_name' => 'PT Kamera Rapi',
            'client_contact' => '08123456789 / finance@kamera.test',
        ]);
    }

    public function test_direct_project_create_does_not_require_contract_value_or_payment_terms(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $customer = CrmCustomer::query()->create([
            'code' => 'CUST-9010',
            'name' => 'Direct Customer',
            'company' => 'PT Langsung',
            'source' => 'manual',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Project Langsung',
                'crm_customer_id' => $customer->id,
                'project_type' => 'system_website_development',
                'status' => 'negosiasi',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.index'));

        $project = Project::query()->where('name', 'Project Langsung')->firstOrFail();

        $this->assertSame(0.0, (float) $project->total_value);
        $this->assertSame(0, $project->payments()->count());

        $this
            ->actingAs($user)
            ->put(route('projects.update', $project), [
                'name' => 'Project Langsung Revisi',
                'crm_customer_id' => $customer->id,
                'project_type' => 'system_website_development',
                'total_value' => 0,
                'status' => 'negosiasi',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.show', $project));

        $project->refresh();
        $this->assertSame('Project Langsung Revisi', $project->name);
        $this->assertSame(0, $project->payments()->count());
    }

    public function test_project_update_refreshes_client_snapshot_from_crm_customer(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $oldCustomer = CrmCustomer::query()->create([
            'code' => 'CUST-9002',
            'name' => 'Old Customer',
            'company' => 'PT Lama',
            'source' => 'manual',
            'is_active' => true,
        ]);
        $newCustomer = CrmCustomer::query()->create([
            'code' => 'CUST-9003',
            'name' => 'Ibu Ani',
            'company' => null,
            'email' => 'ani@example.test',
            'phone' => null,
            'source' => 'manual',
            'is_active' => true,
        ]);
        $project = Project::query()->create([
            'name' => 'Website Company Profile',
            'crm_customer_id' => $oldCustomer->id,
            'client_name' => 'PT Lama',
            'total_value' => 8000000,
            'status' => 'negosiasi',
        ]);

        $this
            ->actingAs($user)
            ->put(route('projects.update', $project), [
                'name' => 'Website Company Profile',
                'crm_customer_id' => $newCustomer->id,
                'project_type' => 'system_website_development',
                'total_value' => 8000000,
                'status' => 'negosiasi',
                'payments' => [
                    ['percentage' => 100, 'note' => 'Pelunasan'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'crm_customer_id' => $newCustomer->id,
            'client_name' => 'Ibu Ani',
            'client_contact' => 'ani@example.test',
        ]);
    }

    public function test_project_create_accepts_active_type_from_project_type_master(): void
    {
        $this->disableErpMiddleware();

        ProjectType::query()->create([
            'key' => 'network_installation',
            'label' => 'Network Installation',
            'supports_budget_items' => true,
            'supports_project_board' => false,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 30,
        ]);

        $user = User::factory()->create();
        $customer = CrmCustomer::query()->create([
            'code' => 'CUST-9011',
            'name' => 'Network Customer',
            'company' => 'PT Network Rapi',
            'source' => 'manual',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Instalasi Jaringan Cabang',
                'crm_customer_id' => $customer->id,
                'project_type' => 'network_installation',
                'status' => 'negosiasi',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'name' => 'Instalasi Jaringan Cabang',
            'project_type' => 'network_installation',
        ]);
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);
    }
}
