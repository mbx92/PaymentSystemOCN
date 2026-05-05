<?php

namespace App\Http\Controllers;

use App\Models\CashIn;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CashInController extends Controller
{
    public function index(Request $request)
    {
        $query = CashIn::with('project', 'creator')
            ->when(
                $request->filled('project_id') && Str::isUuid($request->project_id),
                fn ($q) => $q->where('project_id', $request->project_id)
            )
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->date_from, fn ($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('date', '<=', $request->date_to));

        $total = (float) (clone $query)->sum('amount');

        $cashIns = $query->latest('date')->paginate(15)->withQueryString()
            ->through(fn ($c) => [
                'id'           => $c->id,
                'project_name' => $c->project->name,
                'category'     => $c->category,
                'amount'       => (float) $c->amount,
                'date'         => $c->date->format('Y-m-d'),
                'note'         => $c->note,
                'creator_name' => $c->creator->name,
            ]);

        $projects = Project::orderBy('name')->get(['id', 'name']);

        return Inertia::render('CashIn/Index', [
            'cashIns'  => $cashIns,
            'total'    => $total,
            'projects' => $projects,
            'filters'  => $request->only(['project_id', 'category', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|uuid|exists:projects,id',
            'category'   => 'required|in:pendapatan_jasa,lainnya',
            'amount'     => 'required|numeric|min:1',
            'date'       => 'required|date',
            'note'       => 'nullable|string',
        ]);

        $validated['created_by'] = Auth::id();
        CashIn::create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil ditambahkan.']);
    }

    public function update(Request $request, CashIn $cashIn)
    {
        $validated = $request->validate([
            'project_id' => 'required|uuid|exists:projects,id',
            'category'   => 'required|in:pendapatan_jasa,lainnya',
            'amount'     => 'required|numeric|min:1',
            'date'       => 'required|date',
            'note'       => 'nullable|string',
        ]);

        $cashIn->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil diperbarui.']);
    }

    public function destroy(CashIn $cashIn)
    {
        $cashIn->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil dihapus.']);
    }
}
