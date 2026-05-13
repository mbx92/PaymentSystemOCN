<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectLegalFolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_show_does_not_create_legal_folder(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject('Folder Test '.Str::uuid());
        $relative = 'Project Contracts/'.Str::slug($project->name);
        $target = storage_path('app/legal-vault/'.str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (File::isDirectory($target)) {
            File::deleteDirectory($target);
        }

        $response = $this->actingAs($user)->get(route('projects.show', $project));

        $response->assertOk();
        $this->assertFalse(File::isDirectory($target));
        $this->assertNull($project->fresh()->legal_vault_path);
    }

    public function test_create_legal_folder_persists_project_path(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $project = $this->createProject('Folder Test '.Str::uuid());
        $relative = 'Project Contracts/'.Str::slug($project->name);
        $target = storage_path('app/legal-vault/'.str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (File::isDirectory($target)) {
            File::deleteDirectory($target);
        }

        $response = $this->actingAs($user)->post(route('projects.legal-folder.create', $project));

        $response->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(File::isDirectory($target));
        $this->assertSame($relative, $project->fresh()->legal_vault_path);
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\ErpMaintenanceMode::class,
            \App\Http\Middleware\LogErpActivity::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }

    private function createProject(string $name): Project
    {
        $project = new Project([
            'name' => $name,
            'client_name' => 'Client',
            'total_value' => 1000000,
            'status' => 'berjalan',
        ]);
        $project->id = (string) Str::uuid();
        $project->save();

        return $project;
    }
}

