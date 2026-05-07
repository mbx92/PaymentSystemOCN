<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\ProjectBudget;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectBudgetController extends Controller
{
    private function mapBudget(ProjectBudget $budget): array
    {
        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'client_name' => $budget->client_name,
            'client_contact' => $budget->client_contact,
            'project_type' => $budget->project_type,
            'estimated_value' => (float) $budget->estimated_value,
            'cctv_items' => $budget->cctv_items ?? [],
            'description' => $budget->description,
            'status' => $budget->status,
            'deal_at' => $budget->deal_at?->format('Y-m-d H:i'),
            'converted_project_id' => $budget->converted_project_id,
            'created_at' => $budget->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function validateStorePayload(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'project_type' => 'required|in:cctv_installation,system_website_development',
            'estimated_value' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
    }

    /**
     * Hapus baris item kosong sebelum validasi agar tidak memicu required_with pada indeks 0.
     */
    private function mergeFilteredCctvItems(Request $request): void
    {
        $raw = $request->input('cctv_items');
        if (! is_array($raw)) {
            return;
        }

        $filtered = collect($raw)
            ->filter(fn ($row) => is_array($row) && ! empty(trim((string) ($row['name'] ?? ''))))
            ->values()
            ->all();

        $request->merge(['cctv_items' => $filtered]);
    }

    private function validateUpdatePayload(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'project_type' => 'required|in:cctv_installation,system_website_development',
            'estimated_value' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'cctv_items' => 'nullable|array',
            'cctv_items.*.name' => 'required|string|max:255',
            'cctv_items.*.qty' => 'required|numeric|min:0.01',
            'cctv_items.*.unit_price' => 'required|numeric|min:0',
        ]);
    }

    private function normalizeStorePayload(array $validated): array
    {
        return $validated + ['cctv_items' => []];
    }

    private function normalizeUpdatePayload(array $validated): array
    {
        if (($validated['project_type'] ?? null) !== 'cctv_installation') {
            return $validated + ['cctv_items' => []];
        }

        $cctvItems = collect($validated['cctv_items'] ?? [])
            ->filter(fn (array $row) => ! empty($row['name']) && (float) ($row['qty'] ?? 0) > 0)
            ->map(fn (array $row) => [
                'name' => $row['name'],
                'qty' => (float) $row['qty'],
                'unit_price' => (float) $row['unit_price'],
            ])
            ->values()
            ->all();

        if (! empty($cctvItems)) {
            $validated['estimated_value'] = collect($cctvItems)->sum(fn (array $row) => $row['qty'] * $row['unit_price']);
        }

        return $validated + ['cctv_items' => $cctvItems];
    }

    public function index(): Response
    {
        $budgets = ProjectBudget::query()
            ->latest()
            ->get()
            ->map(fn (ProjectBudget $budget) => $this->mapBudget($budget));

        return Inertia::render('Projects/Budgets', [
            'budgets' => $budgets,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->normalizeStorePayload($this->validateStorePayload($request));
        ProjectBudget::query()->create($payload + ['status' => 'draft']);

        return back()->with('flash', ['type' => 'success', 'message' => 'Budget project berhasil ditambahkan.']);
    }

    public function show(ProjectBudget $budget): Response
    {
        return Inertia::render('Projects/BudgetShow', [
            'budget' => $this->mapBudget($budget),
            'cctv_products' => MasterProduct::query()
                ->where('status', 'active')
                ->whereIn('sales_channel', ['project', 'both'])
                ->orderBy('name')
                ->get(['id', 'sku', 'barcode', 'name', 'uom', 'selling_price']),
        ]);
    }

    public function update(Request $request, ProjectBudget $budget)
    {
        if ($budget->status === 'converted') {
            throw ValidationException::withMessages([
                'budget' => 'Budget yang sudah di-convert tidak bisa diedit.',
            ]);
        }

        if ($request->input('project_type') !== 'cctv_installation') {
            $request->merge(['cctv_items' => []]);
        } else {
            $this->mergeFilteredCctvItems($request);
        }

        $payload = $this->normalizeUpdatePayload($this->validateUpdatePayload($request));
        $budget->update($payload);

        return back()->with('flash', ['type' => 'success', 'message' => 'Budget berhasil diperbarui.']);
    }

    public function markDeal(ProjectBudget $budget)
    {
        if ($budget->status === 'converted') {
            throw ValidationException::withMessages([
                'budget' => 'Budget sudah di-convert menjadi project.',
            ]);
        }

        $budget->update([
            'status' => 'deal',
            'deal_at' => now(),
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Budget ditandai deal.']);
    }

    public function convert(ProjectBudget $budget)
    {
        if ($budget->status !== 'deal') {
            throw ValidationException::withMessages([
                'budget' => 'Hanya budget berstatus deal yang bisa di-convert.',
            ]);
        }
        if ($budget->converted_project_id) {
            throw ValidationException::withMessages([
                'budget' => 'Budget ini sudah pernah di-convert.',
            ]);
        }

        DB::transaction(function () use ($budget): void {
            $project = Project::query()->create([
                'name' => $budget->name,
                'client_name' => $budget->client_name,
                'client_contact' => $budget->client_contact,
                'project_type' => $budget->project_type,
                'total_value' => $budget->estimated_value,
                'status' => 'negosiasi',
                'description' => $budget->description,
            ]);

            $budget->update([
                'status' => 'converted',
                'converted_project_id' => $project->id,
            ]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Budget berhasil di-convert menjadi project negosiasi.']);
    }

    public function pdf(ProjectBudget $budget)
    {
        $pdf = Pdf::loadView('pdf.project-budget', [
            'budget' => $budget,
            'items' => collect($budget->cctv_items ?? []),
            'generatedAt' => now(),
        ])->setPaper('a4');

        $filename = 'budget-project-'.$budget->id.'.pdf';

        return $pdf->download($filename);
    }
}
