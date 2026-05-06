<?php

namespace App\Http\Controllers;

use App\Models\TeamDistribution;
use App\Models\TeamRole;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRoleController extends Controller
{
    public function index(): Response
    {
        $this->ensureDefaults();

        return Inertia::render('Projects/Roles', [
            'roles' => TeamRole::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:team_roles,name',
        ]);

        TeamRole::query()->create([
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Role tim berhasil ditambahkan.']);
    }

    public function destroy(TeamRole $teamRole)
    {
        $isUsed = TeamDistribution::query()->where('role_in_project', $teamRole->name)->exists();
        if ($isUsed) {
            throw ValidationException::withMessages([
                'role' => 'Role masih dipakai dalam pembagian tim project.',
            ]);
        }

        $teamRole->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Role tim berhasil dihapus.']);
    }

    private function ensureDefaults(): void
    {
        if (TeamRole::query()->exists()) {
            return;
        }

        foreach (['Lead', 'Developer', 'Designer', 'QA'] as $name) {
            TeamRole::query()->create(['name' => $name, 'is_active' => true]);
        }
    }
}

