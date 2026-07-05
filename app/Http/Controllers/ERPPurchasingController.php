<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Purchasing\Models\GoodsReceipt;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Purchasing\Models\PurchaseOrderLine;
use App\ERP\Purchasing\Models\Vendor;
use App\ERP\Shared\Enums\DocumentStatus;
use App\ERP\Shared\Services\DocumentNumberService;
use App\ERP\Shared\Services\ErpSystemLogger;
use App\Models\CategoryCoaMapping;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductStockMovement;
use App\Models\ProjectMaterial;
use App\Services\ProjectMaterialReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ERPPurchasingController extends Controller
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
        private readonly ErpSystemLogger $systemLogger,
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    public function suppliers(Request $request): Response
    {
        $query = Vendor::query()->orderBy('name');
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(fn ($inner) => $inner
                ->where('code', 'like', '%'.$q.'%')
                ->orWhere('name', 'like', '%'.$q.'%')
                ->orWhere('phone', 'like', '%'.$q.'%'));
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $suppliers = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (Vendor $vendor) => [
                'code' => $vendor->code,
                'name' => $vendor->name,
                'phone' => $vendor->phone,
                'lead_time_days' => (int) ($vendor->lead_time_days ?? 7),
                'status' => $vendor->is_active ? 'active' : 'void',
            ]);

        return Inertia::render('ERP/Purchasing/Suppliers', [
            'suppliers' => $suppliers,
            'highlight' => $request->query('highlight'),
            'filters' => $this->filtersWithPerPage($request, ['q', 'status']),
        ]);
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'nullable|email|max:120',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:64',
            'payment_terms' => 'nullable|string|max:40',
            'lead_time_days' => 'required|integer|min:1|max:365',
        ]);

        $code = $this->documentNumberService->next('purchasing', 'supplier_code', [
            'prefix' => 'SUP',
            'padding_length' => 3,
        ]);

        Vendor::query()->create([
            ...$validated,
            'code' => $code,
            'is_active' => true,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Supplier baru berhasil ditambahkan.']);
    }

    public function supplierShow(Vendor $supplier): Response
    {
        return Inertia::render('ERP/Purchasing/SupplierShow', [
            'detail' => [
                'id' => $supplier->getKey(),
                'code' => $supplier->code,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'tax_id' => $supplier->tax_id,
                'lead_time_days' => (int) ($supplier->lead_time_days ?? 7),
                'status' => $supplier->is_active ? 'active' : 'void',
                'is_active' => (bool) $supplier->is_active,
                'payment_terms' => $supplier->payment_terms ?? 'Net 14',
                'notes' => $supplier->notes,
            ],
        ]);
    }

    public function updateSupplier(Request $request, Vendor $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'nullable|email|max:120',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:64',
            'payment_terms' => 'nullable|string|max:40',
            'lead_time_days' => 'required|integer|min:1|max:365',
            'notes' => 'nullable|string|max:4000',
            'is_active' => 'required|boolean',
        ]);

        $supplier->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Supplier berhasil diperbarui.']);
    }

    public function purchaseOrders(Request $request): Response
    {
        $query = PurchaseOrder::query()
            ->with('vendor')
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($request->filled('supplier')) {
            $query->whereHas('vendor', fn ($q) => $q->where('code', $request->string('supplier')));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($inner) use ($q): void {
                $inner->where('number', 'like', '%'.$q.'%')
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', '%'.$q.'%'));
            });
        }

        $purchaseOrders = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (PurchaseOrder $po) => [
                'number' => $po->number,
                'supplier' => $po->vendor?->name,
                'supplier_code' => $po->vendor?->code,
                'eta' => $po->eta_date?->toDateString(),
                'amount' => (float) $po->total_amount,
                'po_category' => $po->po_category ?? 'inventory',
                'status' => $po->status->value,
            ]);

        return Inertia::render('ERP/Purchasing/PurchaseOrders', [
            'purchaseOrders' => $purchaseOrders,
            'supplierFilter' => $request->query('supplier'),
            'filters' => $this->filtersWithPerPage($request, ['supplier', 'status', 'q']),
            'suppliers' => Vendor::query()->orderBy('name')->get(['code', 'name']),
            'products' => MasterProduct::query()
                ->where('status', 'active')
                ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
                ->orderBy('name')
                ->get(['id', 'sku', 'barcode', 'name', 'uom', 'selling_price']),
        ]);
    }

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        $baseValidated = $request->validate([
            'vendor_code' => 'required|string|exists:vendors,code',
            'eta_date' => 'nullable|date',
            'order_date' => 'required|date',
            'po_category' => 'nullable|in:inventory,expense',
            'notes' => 'nullable|string',
        ]);
        $validatedLines = $this->validatePurchaseOrderLines($request, linesRequired: false);

        $vendor = Vendor::query()->where('code', $baseValidated['vendor_code'])->firstOrFail();
        $lines = $this->buildPurchaseOrderLines($validatedLines['lines'] ?? []);
        $totalAmount = (float) $lines->sum('line_total');

        $poNumber = DB::transaction(function () use ($baseValidated, $vendor, $lines, $totalAmount): string {
            $number = $this->documentNumberService->next('purchasing', 'purchase_order', [
                'prefix' => 'PO',
                'padding_length' => 6,
            ]);
            $po = PurchaseOrder::query()->create([
                'number' => $number,
                'vendor_id' => $vendor->id,
                'order_date' => $baseValidated['order_date'],
                'eta_date' => $baseValidated['eta_date'] ?? null,
                'total_amount' => $totalAmount,
                'po_category' => $baseValidated['po_category'] ?? 'inventory',
                'status' => DocumentStatus::Draft,
                'notes' => $baseValidated['notes'] ?? null,
            ]);
            foreach ($lines as $line) {
                $po->lines()->create($line);
            }

            return $po->number;
        });

        return redirect()
            ->route('erp.purchasing.purchase-orders.show', $poNumber)
            ->with('flash', ['type' => 'success', 'message' => 'Purchase Order berhasil dibuat.']);
    }

    public function purchaseOrderShow(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['vendor', 'lines.product']);

        return Inertia::render('ERP/Purchasing/PurchaseOrderShow', [
            'detail' => [
                'number' => $purchaseOrder->number,
                'supplier_code' => $purchaseOrder->vendor?->code,
                'supplier_name' => $purchaseOrder->vendor?->name,
                'eta' => $purchaseOrder->eta_date?->toDateString(),
                'amount' => (float) $purchaseOrder->total_amount,
                'po_category' => $purchaseOrder->po_category ?? 'inventory',
                'status' => $purchaseOrder->status->value,
                'created_at' => $purchaseOrder->order_date?->toDateString(),
                'notes' => $purchaseOrder->notes,
                'lines' => $purchaseOrder->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'product_id' => $line->master_product_id,
                    'sku' => $line->product?->sku,
                    'name' => $line->product?->name,
                    'qty' => (float) $line->qty,
                    'received_qty' => (float) $line->received_qty,
                    'remaining_qty' => max((float) $line->qty - (float) $line->received_qty, 0),
                    'uom' => $line->product?->uom,
                    'unit_price' => (float) $line->unit_price,
                    'subtotal' => (float) $line->line_total,
                ]),
            ],
            'suppliers' => Vendor::query()->orderBy('name')->get(['code', 'name']),
            'products' => MasterProduct::query()
                ->where('status', 'active')
                ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
                ->orderBy('name')
                ->get(['id', 'sku', 'barcode', 'name', 'uom', 'selling_price']),
        ]);
    }

    public function updatePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->isEditable()) {
            return back()->with('flash', ['type' => 'warning', 'message' => 'PO hanya bisa diubah saat status draft/submitted.']);
        }

        $baseValidated = $request->validate([
            'vendor_code' => 'required|string|exists:vendors,code',
            'eta_date' => 'nullable|date',
            'order_date' => 'required|date',
            'po_category' => 'nullable|in:inventory,expense',
            'notes' => 'nullable|string',
        ]);

        $validatedLines = $this->validatePurchaseOrderLines($request);

        $vendor = Vendor::query()->where('code', $baseValidated['vendor_code'])->firstOrFail();
        $lines = $this->buildPurchaseOrderLines($validatedLines['lines']);

        $totalAmount = (float) $lines->sum('line_total');

        DB::transaction(function () use ($purchaseOrder, $baseValidated, $vendor, $lines, $totalAmount): void {
            $purchaseOrder->update([
                'vendor_id' => $vendor->id,
                'order_date' => $baseValidated['order_date'],
                'eta_date' => $baseValidated['eta_date'] ?? null,
                'total_amount' => $totalAmount,
                'po_category' => $baseValidated['po_category'] ?? $purchaseOrder->po_category,
                'notes' => $baseValidated['notes'] ?? null,
            ]);

            $existingLineIds = $purchaseOrder->lines()->pluck('id');
            $incomingLineIds = $lines->pluck('id')->filter();
            $purchaseOrder->lines()->whereNotIn('id', $incomingLineIds)->delete();
            foreach ($lines as $line) {
                if (isset($line['id']) && $existingLineIds->contains($line['id'])) {
                    $purchaseOrder->lines()->where('id', $line['id'])->update($line);
                } else {
                    $purchaseOrder->lines()->create($line);
                }
            }
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Data Purchase Order berhasil diperbarui.']);
    }

    private function validatePurchaseOrderLines(Request $request, bool $linesRequired = true): array
    {
        $linesRule = $linesRequired ? 'required|array|min:1' : 'nullable|array';

        return $request->validate([
            'lines' => $linesRule,
            'lines.*.id' => 'nullable|integer|exists:purchase_order_lines,id',
            'lines.*.product_id' => [
                'required',
                'integer',
                Rule::exists('master_products', 'id')->where(fn ($query) => $query->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)),
            ],
            'lines.*.qty' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0.01',
        ]);
    }

    private function buildPurchaseOrderLines(array $lineInputs): Collection
    {
        return collect($lineInputs)
            ->map(function (array $line): array {
                $product = MasterProduct::query()
                    ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
                    ->findOrFail((int) $line['product_id']);
                $qty = (float) $line['qty'];
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = $qty * $unitPrice;

                return [
                    'master_product_id' => $product->id,
                    'qty' => $qty,
                    'line_total' => $lineTotal,
                ];
            })
            ->groupBy('master_product_id')
            ->map(function (Collection $group): array {
                $qty = (float) $group->sum('qty');
                $lineTotal = (float) $group->sum('line_total');
                $unitPrice = $qty > 0 ? $lineTotal / $qty : 0;

                return [
                    'master_product_id' => $group->first()['master_product_id'],
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            })
            ->values();
    }

    public function goodsReceipts(Request $request): Response
    {
        $query = GoodsReceipt::query()
            ->with(['purchaseOrder', 'warehouse'])
            ->orderByDesc('received_date')
            ->orderByDesc('id');

        if ($request->filled('po')) {
            $query->whereHas('purchaseOrder', fn ($q) => $q->where('number', $request->string('po')));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($inner) use ($q): void {
                $inner->where('number', 'like', '%'.$q.'%')
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('number', 'like', '%'.$q.'%'));
            });
        }

        $receipts = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (GoodsReceipt $receipt) => [
                'number' => $receipt->number,
                'po_number' => $receipt->purchaseOrder?->number,
                'received_date' => $receipt->received_date?->toDateString(),
                'items' => $receipt->lines()->count(),
                'status' => $receipt->status->value,
            ]);

        return Inertia::render('ERP/Purchasing/GoodsReceipts', [
            'receipts' => $receipts,
            'poFilter' => $request->query('po'),
            'filters' => $this->filtersWithPerPage($request, ['po', 'status', 'q']),
            'purchaseOrders' => PurchaseOrder::query()
                ->where('status', DocumentStatus::Approved->value)
                ->whereHas('lines', fn ($q) => $q->whereRaw('qty > received_qty'))
                ->with('lines.product')
                ->orderByDesc('order_date')
                ->get()
                ->map(fn (PurchaseOrder $po) => [
                    'number' => $po->number,
                    'lines' => $po->lines->map(fn ($line) => [
                        'product_id' => $line->master_product_id,
                        'sku' => $line->product?->sku,
                        'name' => $line->product?->name,
                        'uom' => $line->product?->uom,
                        'ordered_qty' => (float) $line->qty,
                        'received_qty' => (float) $line->received_qty,
                        'remaining_qty' => max((float) $line->qty - (float) $line->received_qty, 0),
                    ])->values(),
                ]),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function storeGoodsReceipt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_number' => 'required|string|exists:purchase_orders,number',
            'received_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'status' => 'required|in:approved',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer|exists:master_products,id',
            'lines.*.qty_received' => 'required|numeric|min:0',
        ]);

        $purchaseOrder = PurchaseOrder::query()
            ->with('lines.product')
            ->where('number', $validated['purchase_order_number'])
            ->firstOrFail();

        $requestedLines = collect($validated['lines'])
            ->keyBy(fn (array $line) => (int) $line['product_id']);

        $purchaseOrder->lines()
            ->whereIn('master_product_id', $requestedLines->keys())
            ->lockForUpdate()
            ->get();

        $linePayloads = $purchaseOrder->lines
            ->map(function ($poLine) use ($requestedLines) {
                $requestedQty = (float) ($requestedLines->get($poLine->master_product_id)['qty_received'] ?? 0);
                $remaining = max((float) $poLine->qty - (float) $poLine->received_qty, 0);

                if ($requestedQty > $remaining) {
                    throw ValidationException::withMessages([
                        'lines' => 'Qty penerimaan melebihi sisa PO untuk produk '.$poLine->product?->name.'.',
                    ]);
                }

                return [
                    'master_product_id' => $poLine->master_product_id,
                    'qty_received' => $requestedQty,
                ];
            })
            ->filter(fn (array $line) => $line['qty_received'] > 0)
            ->values();

        if ($linePayloads->isEmpty()) {
            return back()->with('flash', ['type' => 'warning', 'message' => 'Isi minimal satu item dengan qty terima > 0.']);
        }

        DB::transaction(function () use ($validated, $purchaseOrder, $linePayloads): void {
            $number = $this->documentNumberService->next('purchasing', 'goods_receipt', [
                'prefix' => 'GRN',
                'padding_length' => 6,
            ]);
            $warehouse = Warehouse::query()->find($validated['warehouse_id']);
            $receipt = GoodsReceipt::query()->create([
                'number' => $number,
                'purchase_order_id' => $purchaseOrder->id,
                'received_date' => $validated['received_date'],
                'warehouse_id' => $validated['warehouse_id'],
                'warehouse_name' => $warehouse?->name ?? 'Warehouse',
                'status' => $validated['status'],
            ]);

            foreach ($linePayloads as $line) {
                $receipt->lines()->create([
                    'master_product_id' => $line['master_product_id'],
                    'qty_received' => $line['qty_received'],
                ]);
            }
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Penerimaan barang berhasil ditambahkan.']);
    }

    public function goodsReceiptShow(GoodsReceipt $goodsReceipt): Response
    {
        $goodsReceipt->load(['purchaseOrder', 'warehouse', 'lines.product']);

        return Inertia::render('ERP/Purchasing/GoodsReceiptShow', [
            'detail' => [
                'number' => $goodsReceipt->number,
                'po_number' => $goodsReceipt->purchaseOrder?->number,
                'received_date' => $goodsReceipt->received_date?->toDateString(),
                'warehouse' => $goodsReceipt->warehouse?->name ?? $goodsReceipt->warehouse_name,
                'warehouse_id' => $goodsReceipt->warehouse_id,
                'status' => $goodsReceipt->status->value,
                'can_reopen' => $goodsReceipt->status === DocumentStatus::Posted,
                'lines' => $goodsReceipt->lines->map(fn ($line) => [
                    'sku' => $line->product?->sku,
                    'name' => $line->product?->name,
                    'qty_received' => (float) $line->qty_received,
                    'uom' => $line->product?->uom,
                ]),
            ],
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function reorderPlanning(Request $request): Response
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);
        $warehouseId = $validated['warehouse_id'] ?? null;

        $projectShortages = ProjectMaterial::query()
            ->select('master_product_id')
            ->selectRaw('SUM(CASE WHEN planned_qty > reserved_qty THEN planned_qty - reserved_qty ELSE 0 END) as shortage_qty')
            ->whereHas('product', fn ($q) => $q->whereIn('product_type', $this->reorderStockProductTypes()))
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['negosiasi', 'berjalan']))
            ->whereRaw('planned_qty > reserved_qty')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->groupBy('master_product_id')
            ->pluck('shortage_qty', 'master_product_id');

        $onOrderQty = PurchaseOrderLine::query()
            ->select('master_product_id')
            ->selectRaw('SUM(qty - received_qty) as on_order_qty')
            ->whereRaw('qty > received_qty')
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [
                DocumentStatus::Draft->value,
                DocumentStatus::Submitted->value,
                DocumentStatus::Approved->value,
            ]))
            ->groupBy('master_product_id')
            ->pluck('on_order_qty', 'master_product_id');

        $products = MasterProduct::query()
            ->whereIn('product_type', $this->reorderStockProductTypes())
            ->when($warehouseId, function ($q) use ($warehouseId): void {
                $q->where(function ($inner) use ($warehouseId): void {
                    $inner->whereHas('warehouseStocks', fn ($stock) => $stock->where('warehouse_id', $warehouseId))
                        ->orWhereHas('projectMaterials', fn ($material) => $material->where('warehouse_id', $warehouseId));
                });
            })
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = $request->string('q')->toString();
                $q->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', '%'.$term.'%')
                        ->orWhere('sku', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('name')
            ->get();

        $onHandById = $this->onHandByProductIdForReorder($products, $warehouseId);

        $reorderSuggestions = $products
            ->map(function (MasterProduct $item) use ($projectShortages, $onOrderQty, $onHandById) {
                $onHand = $onHandById[$item->id] ?? (float) $item->stock;
                $targetStock = $item->min_stock;
                $stockSuggestion = max($targetStock - $onHand, 0);
                $projectShortageQty = (float) ($projectShortages[$item->id] ?? 0);
                $onOrder = (float) ($onOrderQty[$item->id] ?? 0);
                $suggestedQty = max($stockSuggestion + $projectShortageQty - $onOrder, 0);

                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'stock' => $onHand,
                    'min_stock' => $item->min_stock,
                    'total_sold' => $item->total_sold,
                    'suggested_qty' => $suggestedQty,
                    'stock_suggestion_qty' => $stockSuggestion,
                    'project_shortage_qty' => $projectShortageQty,
                    'on_order_qty' => $onOrder,
                    'selling_price' => (float) $item->selling_price,
                ];
            })
            ->filter(fn (array $row) => $row['suggested_qty'] > 0)
            ->sortByDesc('suggested_qty')
            ->take(20)
            ->values();

        return Inertia::render('ERP/Purchasing/ReorderPlanning', [
            'reorderSuggestions' => $reorderSuggestions,
            'filters' => $request->only(['q', 'warehouse_id']),
            'suppliers' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['code', 'name']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function reorderShow(MasterProduct $masterProduct): Response
    {
        $item = $masterProduct;
        abort_if($item->product_type === MasterProduct::PRODUCT_TYPE_SERVICE, 404);
        $onHand = $this->onHandByProductIdForReorder(collect([$item]))[$item->id] ?? (float) $item->stock;
        $dailyUsage = $item->total_sold > 0 ? $item->total_sold / 30 : 0;
        $targetStock = $item->min_stock;
        $stockSuggestion = max($targetStock - $onHand, 0);
        $projectShortageQty = (float) ProjectMaterial::query()
            ->where('master_product_id', $item->id)
            ->whereHas('product', fn ($q) => $q->whereIn('product_type', $this->reorderStockProductTypes()))
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['negosiasi', 'berjalan']))
            ->whereRaw('planned_qty > reserved_qty')
            ->sum(DB::raw('CASE WHEN planned_qty > reserved_qty THEN planned_qty - reserved_qty ELSE 0 END'));
        $onOrder = (float) PurchaseOrderLine::query()
            ->where('master_product_id', $item->id)
            ->whereRaw('qty > received_qty')
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [
                DocumentStatus::Draft->value,
                DocumentStatus::Submitted->value,
                DocumentStatus::Approved->value,
            ]))
            ->sum(DB::raw('qty - received_qty'));
        $suggestedQty = max($stockSuggestion + $projectShortageQty - $onOrder, 0);

        $detail = [
            'id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'stock' => $onHand,
            'min_stock' => $item->min_stock,
            'total_sold' => $item->total_sold,
            'uom' => $item->uom,
            'suggested_qty' => $suggestedQty,
            'target_stock' => $targetStock,
            'daily_usage_est' => round($dailyUsage, 2),
            'stock_suggestion_qty' => $stockSuggestion,
            'project_shortage_qty' => $projectShortageQty,
            'on_order_qty' => $onOrder,
            'selling_price' => (float) $item->selling_price,
        ];

        return Inertia::render('ERP/Purchasing/ReorderShow', [
            'detail' => $detail,
            'suppliers' => Vendor::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['code', 'name']),
        ]);
    }

    public function advancePurchaseOrder(Request $request, string $purchaseOrder): RedirectResponse
    {
        $po = PurchaseOrder::query()->where('number', $purchaseOrder)->firstOrFail();

        $action = $request->input('action', 'submit');
        $status = $po->status->value;

        if ($action === 'submit' && $status === DocumentStatus::Draft->value) {
            if (! $po->lines()->exists()) {
                return redirect()
                    ->route('erp.purchasing.purchase-orders.show', $purchaseOrder)
                    ->with('flash', ['type' => 'warning', 'message' => 'Tambahkan minimal satu item sebelum PO diajukan.']);
            }

            $po->update([
                'status' => DocumentStatus::Submitted,
            ]);

            $this->systemLogger->info('purchasing.po.submitted', 'PO submitted', [
                'user_id' => Auth::id(),
                'path' => $request->path(),
                'method' => $request->method(),
                'po_number' => $po->number,
            ]);

            return redirect()
                ->route('erp.purchasing.purchase-orders.show', $purchaseOrder)
                ->with('flash', ['type' => 'success', 'message' => 'PO berhasil diajukan.']);
        }

        if ($action === 'approve' && $status === DocumentStatus::Submitted->value) {
            $po->update([
                'status' => DocumentStatus::Approved,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ]);

            $this->systemLogger->info('purchasing.po.approved', 'PO approved', [
                'user_id' => Auth::id(),
                'path' => $request->path(),
                'method' => $request->method(),
                'po_number' => $po->number,
            ]);

            return redirect()
                ->route('erp.purchasing.purchase-orders.show', $purchaseOrder)
                ->with('flash', ['type' => 'success', 'message' => 'PO berhasil disetujui.']);
        }

        if ($action === 'void' && in_array($status, [DocumentStatus::Draft->value, DocumentStatus::Submitted->value], true)) {
            $po->update([
                'status' => DocumentStatus::Void,
            ]);

            $this->systemLogger->warning('purchasing.po.void', 'PO voided', [
                'user_id' => Auth::id(),
                'path' => $request->path(),
                'method' => $request->method(),
                'po_number' => $po->number,
            ]);

            return redirect()
                ->route('erp.purchasing.purchase-orders.show', $purchaseOrder)
                ->with('flash', ['type' => 'warning', 'message' => 'PO dibatalkan.']);
        }

        return redirect()
            ->route('erp.purchasing.purchase-orders.show', $purchaseOrder)
            ->with('flash', ['type' => 'info', 'message' => 'Tidak ada perubahan status.']);
    }

    public function advanceGoodsReceipt(Request $request, string $goodsReceipt): RedirectResponse
    {
        $receipt = GoodsReceipt::query()
            ->with(['purchaseOrder.vendor', 'warehouse', 'lines.product'])
            ->where('number', $goodsReceipt)
            ->firstOrFail();

        $action = $request->input('action', 'post_stock');
        $status = $receipt->status->value;

        if ($action === 'post_stock' && $status === DocumentStatus::Approved->value) {
            $validated = $request->validate([
                'warehouse_id' => 'nullable|exists:warehouses,id',
            ]);

            DB::transaction(function () use ($receipt, $validated, $request): void {
                if (! empty($validated['warehouse_id'])) {
                    $warehouse = Warehouse::query()->find((int) $validated['warehouse_id']);
                    $receipt->update([
                        'warehouse_id' => (int) $validated['warehouse_id'],
                        'warehouse_name' => $warehouse?->name ?? $receipt->warehouse_name,
                    ]);
                    $receipt->refresh();
                }

                $receipt->update([
                    'status' => DocumentStatus::Posted,
                    'posted_at' => now(),
                    'posted_by' => Auth::id(),
                ]);

                $isExpensePo = $receipt->purchaseOrder->isExpense();

                foreach ($receipt->lines as $line) {
                    $product = $line->product;
                    if (! $product) {
                        continue;
                    }

                    $poLine = $receipt->purchaseOrder
                        ->lines()
                        ->where('master_product_id', $product->id)
                        ->lockForUpdate()
                        ->first();
                    $remaining = $poLine ? max((float) $poLine->qty - (float) $poLine->received_qty, 0) : 0;
                    if ($remaining < (float) $line->qty_received) {
                        throw ValidationException::withMessages([
                            'action' => 'Qty GRN melebihi sisa PO untuk produk '.$product->name.'.',
                        ]);
                    }

                    $warehouseId = $receipt->warehouse_id;
                    if ($warehouseId) {
                        $row = MasterProductWarehouseStock::query()->firstOrCreate(
                            ['master_product_id' => $product->id, 'warehouse_id' => $warehouseId],
                            ['qty' => 0]
                        );
                        $row->increment('qty', (float) $line->qty_received);

                        $this->allocateProjectMaterialReservations(
                            $product->id,
                            (int) $warehouseId,
                            (float) $line->qty_received,
                        );
                        app(ProjectMaterialReservationService::class)
                            ->syncWarehouseReservation($product->id, (int) $warehouseId);
                    }
                    if (! $warehouseId) {
                        $product->increment('stock', (float) $line->qty_received);
                    }
                    $poLine?->increment('received_qty', (float) $line->qty_received);

                    if (! $isExpensePo && $poLine) {
                        $unitPrice = (float) $poLine->unit_price;
                        $newQty = (float) $line->qty_received;
                        $oldStock = max((float) $product->stock - $newQty, 0);
                        $oldCost = (float) ($product->unit_cost ?? 0);
                        $newAvgCost = $oldStock > 0
                            ? (($oldStock * $oldCost) + ($newQty * $unitPrice)) / ($oldStock + $newQty)
                            : $unitPrice;
                        $product->update(['unit_cost' => $newAvgCost]);
                    }

                    ProductStockMovement::query()->create([
                        'master_product_id' => $product->id,
                        'warehouse_id' => $warehouseId,
                        'movement_date' => now()->toDateString(),
                        'movement_type' => 'purchase_receipt',
                        'qty' => $line->qty_received,
                        'note' => 'Receipt '.$receipt->number,
                    ]);
                }

                $inventoryAccount = Account::query()->where('code', '1201')->firstOrFail();
                $payableAccount = Account::query()->where('code', '2001')->firstOrFail();

                $amount = (float) $receipt->purchaseOrder->total_amount;

                if ($isExpensePo) {
                    $expenseMapping = CategoryCoaMapping::query()
                        ->where('domain', 'purchase_order')
                        ->where('category', 'expense')
                        ->value('account_id');

                    $debitAccountId = $expenseMapping
                        ? (int) $expenseMapping
                        : $inventoryAccount->id;
                } else {
                    $debitAccountId = $inventoryAccount->id;
                }

                $description = $isExpensePo
                    ? 'Posting biaya pembelian '.$receipt->number
                    : 'Posting penerimaan barang '.$receipt->number;

                $entry = $this->glPostingService->post(
                    ErpCompanyResolver::resolveForGlPosting($request),
                    sourceModule: 'purchasing',
                    sourceReference: $receipt->number,
                    description: $description,
                    entryDate: $receipt->received_date->toDateString(),
                    lines: [
                        ['account_id' => $debitAccountId, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $payableAccount->id, 'debit' => 0, 'credit' => $amount],
                    ]
                );

                Payable::query()->create([
                    'vendor_id' => $receipt->purchaseOrder->vendor_id,
                    'purchase_order_id' => $receipt->purchase_order_id,
                    'goods_receipt_id' => $receipt->id,
                    'bill_no' => $this->documentNumberService->next('accounting', 'payable_bill', [
                        'prefix' => 'BILL',
                        'padding_length' => 6,
                    ]),
                    'bill_date' => $receipt->received_date->toDateString(),
                    'due_date' => $receipt->received_date->copy()->addDays(14)->toDateString(),
                    'amount' => $amount,
                    'paid_amount' => 0,
                    'status' => DocumentStatus::Posted,
                    'journal_entry_id' => $entry->id,
                ]);
            });

            $this->systemLogger->info('purchasing.grn.posted', 'Goods receipt posted to stock and AP', [
                'user_id' => Auth::id(),
                'path' => $request->path(),
                'method' => $request->method(),
                'receipt_number' => $receipt->number,
                'purchase_order' => $receipt->purchaseOrder?->number,
            ]);

            return redirect()
                ->route('erp.purchasing.goods-receipts.show', $goodsReceipt)
                ->with('flash', ['type' => 'success', 'message' => 'Penerimaan diposting ke stok dan hutang usaha.']);
        }

        if ($action === 'reopen' && $status === DocumentStatus::Posted->value) {
            DB::transaction(function () use ($receipt): void {
                $receipt->refresh();
                $receipt->load(['purchaseOrder.lines', 'warehouse', 'lines.product']);

                $payable = Payable::query()
                    ->withCount('payments')
                    ->lockForUpdate()
                    ->where('goods_receipt_id', $receipt->id)
                    ->first();

                if ($payable && (((int) $payable->payments_count) > 0 || (float) $payable->paid_amount > 0)) {
                    throw ValidationException::withMessages([
                        'action' => 'GR tidak bisa di-reopen karena hutang suppliernya sudah memiliki pembayaran.',
                    ]);
                }

                foreach ($receipt->lines as $line) {
                    $product = MasterProduct::query()
                        ->lockForUpdate()
                        ->find($line->master_product_id);

                    if (! $product) {
                        continue;
                    }

                    $qty = (float) $line->qty_received;
                    if ($qty <= 0) {
                        continue;
                    }

                    if ((float) $product->stock < $qty) {
                        throw ValidationException::withMessages([
                            'action' => 'Stok total untuk '.$product->name.' sudah tidak cukup untuk membatalkan GR ini.',
                        ]);
                    }

                    $warehouseId = (int) ($receipt->warehouse_id ?? 0);
                    if ($warehouseId > 0) {
                        $warehouseStock = MasterProductWarehouseStock::query()
                            ->where('master_product_id', $product->id)
                            ->where('warehouse_id', $warehouseId)
                            ->lockForUpdate()
                            ->first();

                        if (! $warehouseStock || (float) $warehouseStock->qty < $qty) {
                            throw ValidationException::withMessages([
                                'action' => 'Stok warehouse untuk '.$product->name.' sudah tidak cukup untuk membatalkan GR ini.',
                            ]);
                        }

                        $this->releaseProjectMaterialReservations(
                            $product->id,
                            $warehouseId,
                            $qty,
                        );

                        $warehouseStock->refresh();
                        app(ProjectMaterialReservationService::class)
                            ->syncWarehouseReservation($product->id, $warehouseId);
                        $warehouseStock->refresh();

                        $availableAfterRelease = max((float) $warehouseStock->qty - (float) $warehouseStock->reserved_qty, 0);
                        if ($availableAfterRelease < $qty) {
                            throw ValidationException::withMessages([
                                'action' => 'Sebagian stok '.$product->name.' sudah dipakai / direservasi, jadi GR ini belum bisa di-reopen.',
                            ]);
                        }

                        $warehouseStock->decrement('qty', $qty);
                        app(ProjectMaterialReservationService::class)
                            ->syncWarehouseReservation($product->id, $warehouseId);
                    }

                    if ($warehouseId <= 0) {
                        $product->decrement('stock', $qty);
                    }

                    $poLine = $receipt->purchaseOrder
                        ->lines()
                        ->where('master_product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if ($poLine) {
                        $newReceivedQty = max((float) $poLine->received_qty - $qty, 0);
                        $poLine->update([
                            'received_qty' => number_format($newReceivedQty, 2, '.', ''),
                        ]);
                    }

                    ProductStockMovement::query()->create([
                        'master_product_id' => $product->id,
                        'warehouse_id' => $receipt->warehouse_id,
                        'movement_date' => now()->toDateString(),
                        'movement_type' => 'purchase_reopen_out',
                        'qty' => $qty,
                        'note' => 'Reopen receipt '.$receipt->number,
                    ]);
                }

                if ($payable) {
                    $journalEntryId = $payable->journal_entry_id;
                    $payable->delete();

                    if ($journalEntryId) {
                        $this->createReversalJournalEntry(
                            $journalEntryId,
                            0,
                            'Reopen goods receipt '.$receipt->number,
                            'purchasing_reopen',
                            $receipt->number,
                        );
                    }
                }

                $receipt->update([
                    'status' => DocumentStatus::Approved,
                    'posted_at' => null,
                    'posted_by' => null,
                ]);
            });

            $this->systemLogger->warning('purchasing.grn.reopened', 'Goods receipt reopened', [
                'user_id' => Auth::id(),
                'path' => $request->path(),
                'method' => $request->method(),
                'receipt_number' => $receipt->number,
                'purchase_order' => $receipt->purchaseOrder?->number,
            ]);

            return redirect()
                ->route('erp.purchasing.goods-receipts.show', $goodsReceipt)
                ->with('flash', ['type' => 'success', 'message' => 'GR berhasil di-reopen dan efek postingnya sudah dibalik.']);
        }

        return redirect()
            ->route('erp.purchasing.goods-receipts.show', $goodsReceipt)
            ->with('flash', ['type' => 'info', 'message' => 'Tidak ada perubahan status.']);
    }

    private function allocateProjectMaterialReservations(int $productId, int $warehouseId, float $receivedQty): float
    {
        $remaining = $receivedQty;
        $allocated = 0.0;

        ProjectMaterial::query()
            ->with('project')
            ->where('master_product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['negosiasi', 'berjalan']))
            ->whereRaw('planned_qty > reserved_qty')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(function (ProjectMaterial $material) use (&$remaining, &$allocated): void {
                if ($remaining <= 0) {
                    return;
                }

                $shortage = max((float) $material->planned_qty - (float) $material->reserved_qty, 0);
                $toReserve = min($shortage, $remaining);
                if ($toReserve <= 0) {
                    return;
                }

                $material->reserved_qty = (float) $material->reserved_qty + $toReserve;
                $material->status = $this->projectMaterialStatus($material);
                $material->save();

                $remaining -= $toReserve;
                $allocated += $toReserve;
            });

        return $allocated;
    }

    private function releaseProjectMaterialReservations(int $productId, int $warehouseId, float $qtyToRelease): float
    {
        $remaining = $qtyToRelease;
        $released = 0.0;

        ProjectMaterial::query()
            ->with('project')
            ->where('master_product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereHas('project', fn ($q) => $q->whereIn('status', ['negosiasi', 'berjalan']))
            ->whereRaw('reserved_qty > issued_qty')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get()
            ->each(function (ProjectMaterial $material) use (&$remaining, &$released): void {
                if ($remaining <= 0) {
                    return;
                }

                $outstandingReserved = max((float) $material->reserved_qty - (float) $material->issued_qty, 0);
                if ($outstandingReserved <= 0) {
                    return;
                }

                $toRelease = min($outstandingReserved, $remaining);
                $material->reserved_qty = max(
                    (float) $material->issued_qty,
                    (float) $material->reserved_qty - $toRelease
                );
                $material->status = $this->projectMaterialStatus($material);
                $material->save();

                $remaining -= $toRelease;
                $released += $toRelease;
            });

        return $released;
    }

    /**
     * Stok tersedia untuk logika reorder: jika ada baris master_product_warehouse_stocks,
     * jumlahkan per produk Σ max(qty - reserved_qty, 0) lintas gudang; jika belum ada baris gudang, pakai master_products.stock.
     *
     * @param  Collection<int, MasterProduct>  $products
     * @return array<int, float>
     */
    private function onHandByProductIdForReorder(Collection $products, ?int $warehouseId = null): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $ids = $products->pluck('id')->unique()->values()->all();

        if ($warehouseId) {
            return MasterProductWarehouseStock::query()
                ->whereIn('master_product_id', $ids)
                ->where('warehouse_id', $warehouseId)
                ->select('master_product_id')
                ->selectRaw('SUM(CASE WHEN qty > reserved_qty THEN qty - reserved_qty ELSE 0 END) as available')
                ->groupBy('master_product_id')
                ->pluck('available', 'master_product_id')
                ->map(fn ($value) => (float) $value)
                ->all();
        }

        $warehouseData = MasterProductWarehouseStock::query()
            ->whereIn('master_product_id', $ids)
            ->select('master_product_id')
            ->selectRaw('SUM(CASE WHEN qty > reserved_qty THEN qty - reserved_qty ELSE 0 END) as available')
            ->selectRaw('COUNT(*) as has_rows')
            ->groupBy('master_product_id')
            ->get()
            ->keyBy('master_product_id');

        $out = [];
        foreach ($products as $item) {
            $id = $item->id;
            $out[$id] = $warehouseData->has($id)
                ? (float) $warehouseData[$id]->available
                : (float) $item->stock;
        }

        return $out;
    }

    /**
     * Tipe master produk ber-stok yang masuk perencanaan reorder / PO (termasuk finished_goods dan project_material).
     *
     * @return list<string>
     */
    private function reorderStockProductTypes(): array
    {
        return [
            MasterProduct::PRODUCT_TYPE_FINISHED_GOODS,
            MasterProduct::PRODUCT_TYPE_PROJECT_MATERIAL,
        ];
    }

    private function createReversalJournalEntry(
        int $originalEntryId,
        int $companyId,
        string $description,
        string $sourceModule,
        string $sourceReference,
    ): ?JournalEntry {
        $original = JournalEntry::query()
            ->with('lines')
            ->find($originalEntryId);

        if (! $original || $original->lines->isEmpty()) {
            return null;
        }

        $entryNo = $this->documentNumberService->next('accounting', 'journal_entry_reversal', [
            'prefix' => 'REV',
            'padding_length' => 6,
        ]);

        $reversal = JournalEntry::query()->create([
            'company_id' => $companyId ?: $original->company_id,
            'entry_no' => $entryNo,
            'entry_date' => now()->toDateString(),
            'description' => $description,
            'status' => DocumentStatus::Posted,
            'source_module' => $sourceModule,
            'source_reference' => $sourceReference,
            'posted_at' => now(),
            'posted_by' => Auth::id(),
            'reversed_entry_id' => $original->id,
        ]);

        foreach ($original->lines as $line) {
            JournalLine::query()->create([
                'journal_entry_id' => $reversal->id,
                'account_id' => $line->account_id,
                'description' => $line->description,
                'debit' => $line->credit,
                'credit' => $line->debit,
            ]);
        }

        return $reversal;
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
}
