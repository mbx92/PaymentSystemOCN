<?php

namespace App\Http\Controllers;

use App\ERP\CRM\Models\CrmCustomer;
use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetItem;
use App\Models\ProjectMaterial;
use App\Models\ProjectType;
use App\Services\BudgetCatalogPromotionService;
use App\Services\PdfThemeResolver;
use App\Services\ProjectMaterialReservationService;
use App\Services\SupplierCatalogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectBudgetController extends Controller
{
    private const CUSTOMER_MARKUP_PERCENT = 40;

    private function mapBudget(ProjectBudget $budget): array
    {
        $items = $budget->relationLoaded('items') ? $budget->items : $budget->items()->get();
        $mappedItems = $items->map(fn (ProjectBudgetItem $item): array => [
            'id' => $item->id,
            'master_product_id' => $item->master_product_id,
            'catalog_sheet' => $item->catalog_sheet,
            'catalog_ref' => $item->catalog_ref,
            'catalog_category' => $item->catalog_category,
            'item_type' => $item->item_type,
            'name' => $item->name,
            'uom' => $item->uom,
            'qty' => (float) $item->qty,
            'unit_cost' => (float) $item->unit_cost,
            'unit_price' => (float) $item->unit_price,
            'subtotal_cost' => $item->subtotal_cost,
            'subtotal_price' => $item->subtotal_price,
            'margin_amount' => $item->margin_amount,
            'notes' => $item->notes,
        ])->values();

        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'crm_customer_id' => $budget->crm_customer_id,
            'client_name' => $budget->client_name,
            'client_contact' => $budget->client_contact,
            'project_type' => $budget->project_type,
            'project_type_label' => $budget->projectTypeLabel(),
            'supports_budget_items' => $budget->supportsBudgetItems(),
            'estimated_value' => (float) $budget->estimated_value,
            'cctv_items' => $mappedItems->isNotEmpty() ? $mappedItems : ($budget->cctv_items ?? []),
            'budget_items' => $mappedItems,
            'total_cost' => (float) $mappedItems->sum('subtotal_cost'),
            'total_margin' => (float) $mappedItems->sum('margin_amount'),
            'description' => $budget->description,
            'status' => $budget->status,
            'deal_at' => $budget->deal_at?->format('Y-m-d H:i'),
            'converted_project_id' => $budget->converted_project_id,
            'created_at' => $budget->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function validateStorePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'crm_customer_id' => 'nullable|exists:crm_customers,id',
            'client_name' => 'required_without:crm_customer_id|nullable|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'project_type' => [
                'required',
                Rule::exists('project_types', 'key')->where('is_active', true),
            ],
            'description' => 'nullable|string',
        ]);

        return $this->mergeBudgetClientSnapshot($validated);
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'crm_customer_id' => 'nullable|exists:crm_customers,id',
            'client_name' => 'required_without:crm_customer_id|nullable|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'project_type' => [
                'required',
                Rule::exists('project_types', 'key')->where('is_active', true),
            ],
            'estimated_value' => [
                Rule::requiredIf(fn () => ! $this->projectTypeSupportsBudgetItems($request->input('project_type'))),
                'nullable',
                'numeric',
                'min:0',
            ],
            'description' => 'nullable|string',
            'cctv_items' => 'nullable|array',
            'cctv_items.*.name' => 'required|string|max:255',
            'cctv_items.*.master_product_id' => 'nullable|exists:master_products,id',
            'cctv_items.*.catalog_sheet' => 'nullable|string|max:50',
            'cctv_items.*.catalog_ref' => 'nullable|string|max:100',
            'cctv_items.*.catalog_category' => 'nullable|string|max:100',
            'cctv_items.*.item_type' => 'nullable|in:product,material,service',
            'cctv_items.*.uom' => 'nullable|string|max:30',
            'cctv_items.*.qty' => 'required|numeric|min:0.01',
            'cctv_items.*.unit_cost' => 'nullable|numeric|min:0',
            'cctv_items.*.unit_price' => 'required|numeric|min:0',
            'cctv_items.*.notes' => 'nullable|string|max:1000',
        ]);

        return $this->mergeBudgetClientSnapshot($validated);
    }

    private function normalizeStorePayload(array $validated): array
    {
        return $validated + [
            'estimated_value' => 0,
            'cctv_items' => [],
        ];
    }

    private function normalizeUpdatePayload(array $validated): array
    {
        if (! $this->projectTypeSupportsBudgetItems($validated['project_type'] ?? null)) {
            return $validated + ['cctv_items' => []];
        }

        $cctvItems = collect($validated['cctv_items'] ?? [])
            ->filter(fn (array $row) => ! empty($row['name']) && (float) ($row['qty'] ?? 0) > 0)
            ->map(fn (array $row) => [
                'master_product_id' => $row['master_product_id'] ?? null,
                'catalog_sheet' => $row['catalog_sheet'] ?? null,
                'catalog_ref' => $row['catalog_ref'] ?? null,
                'catalog_category' => $row['catalog_category'] ?? null,
                'item_type' => $row['item_type'] ?? 'material',
                'name' => $row['name'],
                'uom' => $row['uom'] ?? null,
                'qty' => (float) $row['qty'],
                'unit_cost' => (float) ($row['unit_cost'] ?? 0),
                'unit_price' => (float) $row['unit_price'],
                'notes' => $row['notes'] ?? null,
            ])
            ->values()
            ->all();

        if (! empty($cctvItems)) {
            $validated['estimated_value'] = collect($cctvItems)->sum(fn (array $row) => $row['qty'] * $row['unit_price']);
        } elseif ($this->projectTypeSupportsBudgetItems($validated['project_type'] ?? null)) {
            $validated['estimated_value'] = 0;
        }

        return $validated + ['cctv_items' => $cctvItems];
    }

    public function index(Request $request): Response
    {
        $query = ProjectBudget::query()
            ->with(['items', 'projectTypeDefinition'])
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $builder->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('client_name', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')->toString()))
            ->when($request->filled('project_type'), fn ($builder) => $builder->where('project_type', $request->string('project_type')->toString()))
            ->latest();

        $paginator = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString();

        $budgets = new LengthAwarePaginator(
            $paginator->getCollection()->map(fn (ProjectBudget $budget) => $this->mapBudget($budget)),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Projects/Budgets', [
            'budgets' => $budgets,
            'project_types' => ProjectType::activeOptions(),
            'crm_customers' => $this->crmCustomerOptions(),
            'filters' => $this->filtersWithPerPage($request, ['q', 'status', 'project_type']),
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
        $budget->load(['items', 'projectTypeDefinition']);

        return Inertia::render('Projects/BudgetShow', [
            'budget' => $this->mapBudget($budget),
            'cctv_products' => MasterProduct::query()
                ->where('status', 'active')
                ->whereIn('sales_channel', ['project', 'both'])
                ->orderBy('name')
                ->get(['id', 'sku', 'barcode', 'name', 'uom', 'selling_price', 'product_type']),
            'project_types' => ProjectType::activeOptions(),
            'crm_customers' => $this->crmCustomerOptions(),
        ]);
    }

    public function builder(ProjectBudget $budget): Response|RedirectResponse
    {
        $budget->load(['items', 'projectTypeDefinition']);

        if (! $budget->supportsBudgetItems()) {
            return redirect()
                ->route('erp.projects.budgets.show', $budget)
                ->with('flash', ['type' => 'error', 'message' => 'Tipe project ini tidak mendukung RAB itemized.']);
        }

        return Inertia::render('Projects/BudgetBuilder', [
            'budget' => $this->mapBudget($budget),
            'cctv_products' => MasterProduct::query()
                ->where('status', 'active')
                ->whereIn('sales_channel', ['project', 'both'])
                ->orderBy('name')
                ->get(['id', 'sku', 'barcode', 'name', 'uom', 'selling_price', 'unit_cost', 'product_type']),
            'catalog_sheets' => app(SupplierCatalogService::class)->sheets(),
            'can_edit' => $budget->status !== 'converted',
        ]);
    }

    public function customerView(ProjectBudget $budget): Response|RedirectResponse
    {
        $budget->load(['items', 'projectTypeDefinition']);

        if (! $budget->supportsBudgetItems()) {
            return redirect()
                ->route('erp.projects.budgets.show', $budget)
                ->with('flash', ['type' => 'error', 'message' => 'Tipe project ini tidak mendukung tampilan RAB customer.']);
        }

        $customerPayload = $this->mapBudgetForCustomerView($budget);
        $themeResolver = app(PdfThemeResolver::class);
        $resolvedBrand = $themeResolver->brand();

        return Inertia::render('Projects/BudgetCustomerView', [
            'budget' => $customerPayload['budget'],
            'items' => $customerPayload['items'],
            'markup_percent' => self::CUSTOMER_MARKUP_PERCENT,
            'total' => $customerPayload['total'],
            'brand' => [
                'name' => $resolvedBrand['use_title_placeholder']
                    ? $resolvedBrand['title_placeholder']
                    : ($resolvedBrand['title'] ?? config('app.name', 'OCN ERP Suite')),
                'tagline' => $resolvedBrand['use_tagline_placeholder']
                    ? $resolvedBrand['tagline_placeholder']
                    : ($resolvedBrand['tagline'] ?? ''),
                'logo_data_uri' => $resolvedBrand['logo_data_uri'],
            ],
        ]);
    }

    /**
     * @return array{
     *     budget: array{id: int, name: string, client_name: string, project_type_label: string, description: string|null},
     *     items: list<array{name: string, uom: string, qty: float, unit_price: float, subtotal: float}>,
     *     total: float
     * }
     */
    private function mapBudgetForCustomerView(ProjectBudget $budget): array
    {
        $mapped = $this->mapBudget($budget);

        $items = collect($mapped['cctv_items'] ?? [])
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['name'] ?? '')) !== '')
            ->map(function (array $item): array {
                $qty = (float) ($item['qty'] ?? 0);
                $unitPrice = $this->customerUnitPrice($item);

                return [
                    'name' => (string) $item['name'],
                    'uom' => (string) ($item['uom'] ?? 'unit'),
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => round($qty * $unitPrice),
                    'from_catalog' => trim((string) ($item['catalog_ref'] ?? '')) !== '',
                ];
            })
            ->values()
            ->all();

        return [
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'client_name' => $budget->client_name,
                'project_type_label' => $budget->projectTypeLabel(),
                'description' => $budget->description,
            ],
            'items' => $items,
            'total' => (float) collect($items)->sum('subtotal'),
        ];
    }

    /**
     * Master produk & baris manual memakai harga jual; katalog supplier = harga beli (+ markup).
     *
     * @param  array<string, mixed>  $item
     */
    private function customerUnitPrice(array $item): float
    {
        $base = (float) ($item['unit_price'] ?? 0);
        $isCatalog = trim((string) ($item['catalog_ref'] ?? '')) !== '';

        if ($isCatalog) {
            $multiplier = 1 + (self::CUSTOMER_MARKUP_PERCENT / 100);

            return round($base * $multiplier);
        }

        return round($base);
    }

    public function update(Request $request, ProjectBudget $budget)
    {
        if ($budget->status === 'converted') {
            throw ValidationException::withMessages([
                'budget' => 'Budget yang sudah di-convert tidak bisa diedit.',
            ]);
        }

        if (! $this->projectTypeSupportsBudgetItems($request->input('project_type'))) {
            $request->merge(['cctv_items' => []]);
        } else {
            $this->mergeFilteredCctvItems($request);
        }

        $payload = $this->normalizeUpdatePayload($this->validateUpdatePayload($request));

        DB::transaction(function () use ($budget, $payload): void {
            $items = $payload['cctv_items'] ?? [];
            $budget->update($payload);
            $this->replaceBudgetItems($budget, $items);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Budget berhasil diperbarui.']);
    }

    public function markDeal(ProjectBudget $budget)
    {
        if ($budget->status === 'converted') {
            throw ValidationException::withMessages([
                'budget' => 'Budget sudah di-convert menjadi project.',
            ]);
        }

        $promoted = 0;

        DB::transaction(function () use ($budget, &$promoted): void {
            $promoted = app(BudgetCatalogPromotionService::class)->promoteCatalogItems($budget);

            $budget->update([
                'status' => 'deal',
                'deal_at' => now(),
            ]);
        });

        $message = 'Budget ditandai deal.';
        if ($promoted > 0) {
            $message .= " {$promoted} item katalog dipromosikan ke master produk.";
        }

        return back()->with('flash', ['type' => 'success', 'message' => $message]);
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
        if ($budget->supportsBudgetItems() && $budget->items()->count() === 0) {
            throw ValidationException::withMessages([
                'budget' => 'Budget tidak memiliki item. Tambahkan item terlebih dahulu sebelum convert.',
            ]);
        }

        DB::transaction(function () use ($budget): void {
            $promotion = app(BudgetCatalogPromotionService::class);
            $promotion->promoteCatalogItems($budget);
            $promotion->ensureAllItemsHaveMasterProduct($budget->fresh('items'));

            $project = Project::query()->create([
                'name' => $budget->name,
                'crm_customer_id' => $budget->crm_customer_id,
                'client_name' => $budget->client_name,
                'client_contact' => $budget->client_contact,
                'project_type' => $budget->project_type,
                'total_value' => $budget->estimated_value,
                'status' => 'berjalan',
                'started_at' => ($budget->deal_at ?? now())->toDateString(),
                'description' => $budget->description,
            ]);

            $this->syncProjectMaterialsFromBudget($budget->fresh('items'), $project);

            $budget->update([
                'status' => 'converted',
                'converted_project_id' => $project->id,
            ]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Budget berhasil di-convert menjadi project berjalan.']);
    }

    public function pdf(ProjectBudget $budget)
    {
        $budget->load('items');
        $mapped = $this->mapBudget($budget);
        $lineItems = $this->pdfLineItems($budget, $mapped['budget_items']);
        $lineItemsSubtotal = (float) collect($lineItems)->sum('subtotal');
        $grandTotal = $lineItemsSubtotal;
        if ($grandTotal <= 0) {
            $grandTotal = (float) $budget->estimated_value;
        }

        $budgetNumber = $this->budgetNumber($budget);
        $generatedAt = $budget->updated_at ?? $budget->created_at ?? now();

        $themeResolver = app(PdfThemeResolver::class);
        $resolvedBrand = $themeResolver->brand();
        $company = $themeResolver->companyContact();

        $brand = [
            'name' => $resolvedBrand['use_title_placeholder']
                ? $resolvedBrand['title_placeholder']
                : ($resolvedBrand['title'] ?? config('app.name', 'OCN ERP Suite')),
            'tagline' => $resolvedBrand['use_tagline_placeholder']
                ? $resolvedBrand['tagline_placeholder']
                : ($resolvedBrand['tagline'] ?? 'Integrated Business Platform'),
            'logo_data_uri' => $resolvedBrand['logo_data_uri'],
            'address' => $company['use_address_placeholder'] ? $company['address_placeholder'] : $company['address'],
            'phone' => $company['use_phone_placeholder'] ? $company['phone_placeholder'] : $company['phone'],
        ];

        $pdf = Pdf::loadView('pdf.project-budget', [
            'budget' => $budget,
            'lineItems' => $lineItems,
            'lineItemsSubtotal' => $lineItemsSubtotal,
            'grandTotal' => $grandTotal,
            'budgetNumber' => $budgetNumber,
            'brand' => $brand,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4');

        return $pdf->download($budgetNumber.'.pdf');
    }

    private function budgetNumber(ProjectBudget $budget): string
    {
        $period = ($budget->created_at ?? now())->format('Ym');

        return 'BDG-'.$period.'-'.str_pad((string) $budget->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $items
     * @return list<array{name: string, description: string|null, qty: float, uom: string, unit_price: float, subtotal: float}>
     */
    private function pdfLineItems(ProjectBudget $budget, iterable $items): array
    {
        $rows = collect($items)
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['name'] ?? '')) !== '')
            ->map(function (array $item): array {
                $qty = (float) ($item['qty'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $subtotal = (float) ($item['subtotal_price'] ?? ($qty * $unitPrice));
                $description = trim((string) ($item['catalog_category'] ?? ''));
                if ($description === '' && ! empty($item['item_type'])) {
                    $description = ucfirst((string) $item['item_type']);
                }

                return [
                    'name' => (string) $item['name'],
                    'description' => $description !== '' ? $description : null,
                    'qty' => $qty,
                    'uom' => (string) ($item['uom'] ?? 'unit'),
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            })
            ->values();

        if ($rows->isNotEmpty()) {
            return $rows->all();
        }

        if (! $budget->supportsBudgetItems() && (float) $budget->estimated_value > 0) {
            return [[
                'name' => $budget->description
                    ? trim((string) $budget->description)
                    : 'Estimasi project '.$budget->name,
                'description' => $budget->projectTypeLabel() ?: null,
                'qty' => 1,
                'uom' => 'paket',
                'unit_price' => (float) $budget->estimated_value,
                'subtotal' => (float) $budget->estimated_value,
            ]];
        }

        return [];
    }

    private function replaceBudgetItems(ProjectBudget $budget, array $items): void
    {
        $budget->items()->delete();

        foreach ($items as $index => $item) {
            $budget->items()->create([
                'master_product_id' => $item['master_product_id'] ?? null,
                'catalog_sheet' => $item['catalog_sheet'] ?? null,
                'catalog_ref' => $item['catalog_ref'] ?? null,
                'catalog_category' => $item['catalog_category'] ?? null,
                'item_type' => $item['item_type'] ?? 'material',
                'name' => $item['name'],
                'uom' => $item['uom'] ?? null,
                'qty' => $item['qty'],
                'unit_cost' => $item['unit_cost'] ?? 0,
                'unit_price' => $item['unit_price'],
                'notes' => $item['notes'] ?? null,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function syncProjectMaterialsFromBudget(ProjectBudget $budget, Project $project): void
    {
        $budgetItems = $budget->relationLoaded('items')
            ? $budget->items
            : $budget->items()->with('product')->get();

        if ($budgetItems->isEmpty()) {
            $budgetItems = collect($budget->cctv_items ?? [])
                ->map(function (array $item): ProjectBudgetItem {
                    $budgetItem = new ProjectBudgetItem($item);
                    $budgetItem->master_product_id = $item['master_product_id'] ?? null;

                    return $budgetItem;
                });
        }

        $budgetItems
            ->filter(fn (ProjectBudgetItem $item): bool => trim((string) $item->name) !== '' && (float) $item->qty > 0)
            ->each(function (ProjectBudgetItem $item) use ($project): void {
                if (! $item->master_product_id) {
                    return;
                }

                $product = $item->relationLoaded('product')
                    ? $item->product
                    : MasterProduct::query()->find($item->master_product_id);

                if (! $product) {
                    return;
                }

                if (! in_array($product->sales_channel, ['project', 'both'], true)) {
                    $product->update(['sales_channel' => 'project']);
                }

                $warehouse = $this->warehouseForBudgetMaterial($product);
                if (! $warehouse) {
                    return;
                }

                $plannedQty = (float) $item->qty;
                $reservedQty = 0.0;
                $stock = null;

                if ($product->isStockTracked()) {
                    $stock = MasterProductWarehouseStock::query()
                        ->lockForUpdate()
                        ->firstOrCreate(
                            [
                                'master_product_id' => $product->id,
                                'warehouse_id' => $warehouse->id,
                            ],
                            ['qty' => 0, 'reserved_qty' => 0],
                        );

                    $available = max((float) $stock->qty - (float) $stock->reserved_qty, 0);
                    $reservedQty = min($plannedQty, $available);
                }

                $material = ProjectMaterial::query()->firstOrNew([
                    'project_id' => $project->id,
                    'master_product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ]);

                $previousQty = (float) $material->planned_qty;
                $newTotalQty = $previousQty + $plannedQty;
                $material->planned_qty = $newTotalQty;
                $material->reserved_qty = (float) $material->reserved_qty + $reservedQty;
                $material->issued_qty = (float) ($material->issued_qty ?? 0);
                $material->unit_cost = $newTotalQty > 0
                    ? (($previousQty * (float) $material->unit_cost) + ($plannedQty * (float) $item->unit_cost)) / $newTotalQty
                    : (float) $item->unit_cost;
                $material->unit_price = $newTotalQty > 0
                    ? (($previousQty * (float) $material->unit_price) + ($plannedQty * (float) $item->unit_price)) / $newTotalQty
                    : (float) $item->unit_price;
                $material->notes = trim(collect([$item->name, $item->notes])->filter()->implode(' - ')) ?: null;
                $material->status = $product->isStockTracked()
                    ? $this->projectMaterialStatus($material)
                    : 'ready';
                $material->save();

                if ($stock) {
                    app(ProjectMaterialReservationService::class)
                        ->syncWarehouseReservation($product->id, $warehouse->id);
                }
            });
    }

    private function warehouseForBudgetMaterial(MasterProduct $product): ?Warehouse
    {
        $stockWarehouseId = MasterProductWarehouseStock::query()
            ->where('master_product_id', $product->id)
            ->orderByRaw('(qty - reserved_qty) desc')
            ->value('warehouse_id');

        if ($stockWarehouseId) {
            return Warehouse::query()->whereKey($stockWarehouseId)->where('is_active', true)->first();
        }

        return Warehouse::query()
            ->where('is_active', true)
            ->orderByRaw("case when code = 'WH-MAIN' then 0 else 1 end")
            ->orderBy('name')
            ->first();
    }

    private function projectMaterialStatus(ProjectMaterial $material): string
    {
        $plannedQty = (float) $material->planned_qty;
        $reservedQty = (float) $material->reserved_qty;
        $issuedQty = (float) $material->issued_qty;

        if ($plannedQty > 0 && $issuedQty >= $plannedQty) {
            return 'issued';
        }

        if ($plannedQty > 0 && $reservedQty >= $plannedQty) {
            return 'ready';
        }

        if ($reservedQty > 0) {
            return 'partial';
        }

        return 'planned';
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mergeBudgetClientSnapshot(array $validated): array
    {
        if (empty($validated['crm_customer_id'])) {
            $validated['crm_customer_id'] = null;

            return $validated;
        }

        return array_merge($validated, $this->budgetClientSnapshot((int) $validated['crm_customer_id']));
    }

    /**
     * @return array{client_name: string, client_contact: string|null}
     */
    private function budgetClientSnapshot(int $customerId): array
    {
        $customer = CrmCustomer::query()->findOrFail($customerId);

        return [
            'client_name' => $customer->company ?: $customer->name,
            'client_contact' => collect([$customer->phone, $customer->email])->filter()->implode(' / ') ?: null,
        ];
    }

    private function crmCustomerOptions()
    {
        return CrmCustomer::query()
            ->where('is_active', true)
            ->orderBy('company')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'company', 'email', 'phone'])
            ->map(fn (CrmCustomer $customer): array => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'company' => $customer->company,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'display_name' => $customer->company ?: $customer->name,
                'contact' => collect([$customer->phone, $customer->email])->filter()->implode(' / '),
            ])
            ->values();
    }

    private function projectTypeSupportsBudgetItems(?string $key): bool
    {
        if (! is_string($key) || trim($key) === '') {
            return false;
        }

        return (bool) ProjectType::query()
            ->where('key', $key)
            ->value('supports_budget_items');
    }
}
