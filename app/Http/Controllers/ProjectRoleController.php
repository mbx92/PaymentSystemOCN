<?php

namespace App\Http\Controllers;

use App\Models\TeamDistribution;
use App\Models\TeamRole;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureDefaults();

        $query = TeamRole::query()
            ->when($request->filled('q'), fn ($builder) => $builder->where('name', 'like', '%'.$request->string('q')->toString().'%'))
            ->when($request->filled('status'), function ($builder) use ($request): void {
                $status = $request->string('status')->toString();
                if ($status === 'active') {
                    $builder->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $builder->where('is_active', false);
                }
            })
            ->orderBy('name');

        $paginator = $query->paginate($this->resolvedPerPage($request))->withQueryString();
        $roles = new LengthAwarePaginator(
            $paginator->getCollection(),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Projects/Roles', [
            'roles' => $roles,
            'filters' => $this->filtersWithPerPage($request, ['q', 'status']),
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
