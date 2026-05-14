<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectInvoiceDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_invoice_and_sales_note_can_be_downloaded(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = Project::query()->create([
            'name' => 'Project CCTV Demo',
            'client_name' => 'Client Demo',
            'client_contact' => 'client@example.com',
            'project_type' => 'cctv_installation',
            'total_value' => 1500000,
            'status' => 'selesai',
            'finished_at' => '2026-05-14',
            'description' => 'Instalasi CCTV kantor',
        ]);

        ProjectBudget::query()->create([
            'name' => $project->name,
            'client_name' => $project->client_name,
            'client_contact' => $project->client_contact,
            'project_type' => $project->project_type,
            'estimated_value' => 1500000,
            'cctv_items' => [
                ['name' => 'Kamera CCTV 4MP', 'qty' => 2, 'unit_price' => 500000],
                ['name' => 'Jasa instalasi', 'qty' => 1, 'unit_price' => 500000],
            ],
            'status' => 'converted',
            'converted_project_id' => $project->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.sales.project-invoices.download', $project))
            ->assertOk()
            ->assertDownload('INV-PRJ-000001.pdf');

        $this
            ->actingAs($user)
            ->get(route('erp.sales.project-invoices.sales-note', $project))
            ->assertOk()
            ->assertDownload('NOTA-INV-PRJ-000001.pdf');
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ErpMaintenanceMode::class,
            \App\Http\Middleware\LogErpActivity::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }
}
