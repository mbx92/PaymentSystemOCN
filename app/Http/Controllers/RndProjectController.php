<?php

namespace App\Http\Controllers;

use App\ERP\Purchasing\Models\Vendor;
use App\Models\MasterProduct;
use App\Models\RndProject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RndProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $query = RndProject::query()
            ->withSummary()
            ->orderByDesc('created_at');

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($inner) use ($q): void {
                $inner->where('name', 'like', '%'.$q.'%')
                    ->orWhere('category', 'like', '%'.$q.'%')
                    ->orWhere('status', 'like', '%'.$q.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $projects = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (RndProject $project): array => $this->mapProjectRow($project));

        return Inertia::render('Rnd/Index', [
            'projects' => $projects,
            'filters' => $this->filtersWithPerPage($request, ['q', 'status']),
            'statusOptions' => RndProject::STATUSES,
            'summary' => Cache::remember('rnd_summary', now()->addMinutes(5), function () {
                return [
                    'project_count' => RndProject::query()->count(),
                    'active_count' => RndProject::query()->whereIn('status', ['idea', 'research', 'development'])->count(),
                    'total_estimated_budget' => (float) DB::table('rnd_budget_items')->sum('total_price'),
                    'total_actual_spend' => (float) DB::table('rnd_purchases')->sum('total_price'),
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Rnd/Create', $this->formProps([
            'project' => $this->emptyProject(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $project = RndProject::query()->create($this->validatedProject($request));

        return redirect()
            ->route('rnd.projects.show', $project)
            ->with('flash', ['type' => 'success', 'message' => 'Project R&D berhasil dibuat.']);
    }

    public function show(Request $request, RndProject $rndProject): Response
    {
        $rndProject->loadMissing('picUser:id,name');

        $notes = $rndProject->researchNotes()
            ->with(['creator:id,name', 'attachments'])
            ->paginate(10, ['*'], 'notes_page')
            ->withQueryString()
            ->through(fn ($note): array => [
                'id' => $note->id,
                'title' => $note->title,
                'content' => $note->content,
                'created_by' => $note->creator?->name,
                'created_at' => $note->created_at?->format('Y-m-d H:i'),
                'attachments' => $note->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => (int) $attachment->size,
                    'url' => route('storage.serve', ['path' => $attachment->path]),
                ])->values()->all(),
            ]);

        $purchases = $rndProject->purchases()
            ->with(['product:id,sku,name,uom', 'supplier:id,code,name'])
            ->paginate(10, ['*'], 'purchases_page')
            ->withQueryString()
            ->through(fn ($purchase): array => $this->mapPurchaseRow($purchase));

        $budgetItems = $rndProject->budgetItems()->get()->map(fn ($item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'qty' => (float) $item->qty,
            'estimated_unit_price' => (float) $item->estimated_unit_price,
            'total_price' => (float) $item->total_price,
            'sort_order' => (int) $item->sort_order,
        ])->values();

        $outputs = $rndProject->productOutputs()->get()->map(function ($output) use ($rndProject): array {
            $units = (float) $output->units_produced;
            $hppPerUnit = $rndProject->hpp_per_unit_value;

            return [
                'id' => $output->id,
                'name' => $output->name,
                'description' => $output->description,
                'units_produced' => $units,
                'notes' => $output->notes,
                'hpp_per_unit' => $hppPerUnit,
                'allocated_cost' => $hppPerUnit * $units,
            ];
        })->values();

        return Inertia::render('Rnd/Show', array_merge($this->formProps(), [
            'project' => $this->mapProjectDetail($rndProject),
            'notes' => $notes,
            'budgetItems' => $budgetItems,
            'purchases' => $purchases,
            'outputs' => $outputs,
            'summary' => $this->summaryPayload($rndProject),
        ]));
    }

    public function edit(RndProject $rndProject): Response
    {
        return Inertia::render('Rnd/Edit', $this->formProps([
            'project' => $this->mapProjectEdit($rndProject),
        ]));
    }

    public function update(Request $request, RndProject $rndProject): RedirectResponse
    {
        $rndProject->update($this->validatedProject($request));

        return redirect()
            ->route('rnd.projects.show', $rndProject)
            ->with('flash', ['type' => 'success', 'message' => 'Project R&D berhasil diperbarui.']);
    }

    public function destroy(RndProject $rndProject): RedirectResponse
    {
        $notePaths = $rndProject->researchNotes()
            ->with('attachments')
            ->get()
            ->flatMap(fn ($note) => $note->attachments->pluck('path'));
        $receiptPaths = $rndProject->purchases()->pluck('receipt_path')->filter();

        foreach ($notePaths->merge($receiptPaths)->filter() as $path) {
            Storage::disk('public')->delete($path);
        }

        $rndProject->delete();

        return redirect()
            ->route('rnd.dashboard')
            ->with('flash', ['type' => 'success', 'message' => 'Project R&D berhasil dihapus.']);
    }

    private function validatedProject(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:120',
            'status' => ['required', Rule::in(RndProject::STATUSES)],
            'pic_user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
    }

    private function formProps(array $extra = []): array
    {
        return array_merge([
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'statusOptions' => RndProject::STATUSES,
            'productOptions' => MasterProduct::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'uom']),
        ], $extra);
    }

    private function mapProjectRow(RndProject $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'category' => $project->category,
            'status' => $project->status,
            'pic_name' => $project->picUser?->name,
            'start_date' => $project->start_date?->toDateString(),
            'estimated_budget_total' => $project->estimated_budget_total_value,
            'actual_spend_total' => $project->actual_spend_total_value,
            'variance' => $project->variance_value,
            'hpp_per_unit' => $project->hpp_per_unit_value,
        ];
    }

    private function mapProjectDetail(RndProject $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'category' => $project->category,
            'status' => $project->status,
            'pic_user_id' => $project->pic_user_id,
            'pic_name' => $project->picUser?->name,
            'start_date' => $project->start_date?->toDateString(),
            'notes' => $project->notes,
            'created_at' => $project->created_at?->format('Y-m-d H:i'),
            'updated_at' => $project->updated_at?->format('Y-m-d H:i'),
        ];
    }

    private function mapProjectEdit(RndProject $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'category' => $project->category,
            'status' => $project->status,
            'pic_user_id' => $project->pic_user_id,
            'start_date' => $project->start_date?->toDateString(),
            'notes' => $project->notes,
        ];
    }

    private function emptyProject(): array
    {
        return [
            'name' => '',
            'description' => '',
            'category' => '',
            'status' => 'idea',
            'pic_user_id' => '',
            'start_date' => now()->toDateString(),
            'notes' => '',
        ];
    }

    private function mapPurchaseRow($purchase): array
    {
        return [
            'id' => $purchase->id,
            'master_product_id' => $purchase->master_product_id,
            'product_name' => $purchase->product?->name,
            'product_sku' => $purchase->product?->sku,
            'uom' => $purchase->product?->uom,
            'supplier_id' => $purchase->supplier_id,
            'supplier_name' => $purchase->supplier?->name,
            'supplier_code' => $purchase->supplier?->code,
            'qty' => (float) $purchase->qty,
            'unit_price' => (float) $purchase->unit_price,
            'total_price' => (float) $purchase->total_price,
            'category' => $purchase->category,
            'purchase_date' => $purchase->purchase_date?->toDateString(),
            'notes' => $purchase->notes,
            'receipt_url' => $purchase->receipt_path ? route('storage.serve', ['path' => $purchase->receipt_path]) : null,
        ];
    }

    private function summaryPayload(RndProject $project): array
    {
        $project->loadMissing('budgetItems', 'purchases', 'productOutputs');

        return [
            'estimated_budget_total' => $project->estimated_budget_total_value,
            'actual_spend_total' => $project->actual_spend_total_value,
            'alat_total' => $project->alat_total_value,
            'bahan_total' => $project->bahan_total_value,
            'variance' => $project->variance_value,
            'units_produced_total' => $project->units_produced_total_value,
            'hpp_per_unit' => $project->hpp_per_unit_value,
            'note_count' => $project->researchNotes()->count(),
            'budget_item_count' => $project->budgetItems->count(),
            'purchase_count' => $project->purchases->count(),
            'output_count' => $project->productOutputs->count(),
        ];
    }
}
