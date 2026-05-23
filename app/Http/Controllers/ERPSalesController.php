<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Services\CoaSettingService;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Shared\Enums\DocumentStatus;
use App\ERP\Shared\Services\DocumentNumberService;
use App\Models\CashIn;
use App\Models\ErpSetting;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\PaymentMethod;
use App\Models\PosSale;
use App\Models\ProductStockMovement;
use App\Models\Project;
use App\Models\User;
use App\Services\LanEscPosPrinter;
use App\Services\ThermalPosReceiptData;
use App\Services\ThermalPosReceiptRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ERPSalesController extends Controller
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    public function pos(Request $request): Response
    {
        $products = MasterProduct::query()
            ->where('status', 'active')
            ->whereIn('sales_channel', ['pos', 'both'])
            ->with([
                'channelPrices' => fn ($q) => $q->where('status', 'active'),
                'uomMappings' => fn ($q) => $q->where('status', 'active'),
            ])
            ->orderBy('name')
            ->get(['id', 'sku', 'barcode', 'name', 'uom', 'selling_price', 'stock'])
            ->flatMap(function (MasterProduct $product) {
                $baseChannelPrices = $this->productChannelPrices($product);
                $base = [[
                    'id' => 'base-'.$product->id,
                    'master_product_id' => $product->id,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'uom' => $product->uom,
                    'variant_label' => $product->uom,
                    'price' => (float) $product->selling_price,
                    'channel_prices' => $baseChannelPrices,
                    'stock' => $product->stock,
                    'multiplier' => 1,
                ]];

                $mapped = $product->uomMappings->map(function ($mapping) use ($product, $baseChannelPrices) {
                    $operation = $mapping->price_operation ?: 'multiply';
                    $mappedPrice = (float) ($mapping->use_auto_price
                        ? $this->computeMappedPrice((float) $product->selling_price, (float) $mapping->multiplier, $operation)
                        : $mapping->selling_price);

                    return [
                        'id' => 'map-'.$mapping->id,
                        'master_product_id' => $product->id,
                        'sku' => $product->sku.'-'.$mapping->uom_code,
                        'barcode' => $product->barcode,
                        'name' => $product->name,
                        'uom' => $mapping->uom_code,
                        'variant_label' => $mapping->uom_code,
                        'price_operation' => $operation,
                        'price' => $mappedPrice,
                        'channel_prices' => $this->mappedChannelPrices(
                            $baseChannelPrices,
                            $mappedPrice,
                            (float) $mapping->multiplier,
                            $operation,
                            (bool) $mapping->use_auto_price
                        ),
                        'stock' => (int) (($operation === 'divide')
                            ? floor((float) $product->stock * (float) $mapping->multiplier)
                            : floor((float) $product->stock / max((float) $mapping->multiplier, 0.0001))),
                        'multiplier' => (float) $mapping->multiplier,
                    ];
                });

                return collect($base)->concat($mapped);
            })
            ->values();

        return Inertia::render('ERP/Sales/POS', [
            'products' => $products,
            'price_channels' => $this->priceChannels(),
            'fullscreen' => $request->boolean('fullscreen'),
            'payment_methods' => $this->posPaymentMethodsPayload(),
        ]);
    }

    public function posTransactions(Request $request): Response
    {
        $query = PosSale::query()
            ->with(['paymentMethod:id,name', 'soldBy:id,name', 'items:id,pos_sale_id'])
            ->latest('sold_at')
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = $request->string('q')->toString();
                $q->where('number', 'like', '%'.$term.'%')
                    ->orWhere('marketplace_order_code', 'like', '%'.$term.'%')
                    ->orWhereHas('soldBy', fn ($u) => $u->where('name', 'like', '%'.$term.'%'));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('sold_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('sold_at', '<=', $request->date('date_to')));

        $transactions = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (PosSale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'sales_channel_key' => $sale->sales_channel ?: 'retail',
                'sales_channel' => $this->priceChannelLabel($sale->sales_channel ?: 'retail'),
                'marketplace_order_code' => $sale->marketplace_order_code,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i:s'),
                'items_count' => $sale->items->count(),
                'grand_total' => (float) $sale->grand_total,
                'payment_method' => $sale->paymentMethod?->name,
                'cashier' => $sale->soldBy?->name,
                'status' => $sale->status,
            ]);

        return Inertia::render('ERP/Sales/Transactions', [
            'transactions' => $transactions,
            'filters' => $this->filtersWithPerPage($request, ['q', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function posTransactionShow(PosSale $posSale): Response
    {
        $posSale->load(['paymentMethod:id,code,name', 'soldBy:id,name', 'items.product:id,sku,name,uom', 'additionalCharges']);

        return Inertia::render('ERP/Sales/TransactionShow', [
            'detail' => [
                'id' => $posSale->id,
                'number' => $posSale->number,
                'sales_channel' => $posSale->sales_channel ?: 'retail',
                'sales_channel_label' => $this->priceChannelLabel($posSale->sales_channel ?: 'retail'),
                'marketplace_order_code' => $posSale->marketplace_order_code,
                'status' => $posSale->status,
                'sold_at' => $posSale->sold_at?->format('Y-m-d H:i:s'),
                'cashier' => $posSale->soldBy?->name,
                'payment_method_id' => $posSale->payment_method_id,
                'payment_method_name' => $posSale->paymentMethod?->name,
                'gross_total' => (float) $posSale->gross_total,
                'discount_total' => (float) $posSale->discount_total,
                'additional_fee' => (float) $posSale->additional_fee,
                'sales_channel_admin_fee' => (float) ($posSale->sales_channel_admin_fee ?? 0),
                'grand_total' => (float) $posSale->grand_total,
                'cash_paid' => (float) $posSale->cash_paid,
                'change_amount' => (float) $posSale->change_amount,
                'additional_charges' => $posSale->additionalCharges->map(fn ($charge) => [
                    'id' => $charge->id,
                    'charge_name' => $charge->charge_name,
                    'amount' => (float) $charge->amount,
                    'kind' => $charge->kind ?? 'add_to_total',
                ]),
                'items' => $posSale->items->map(fn ($item) => [
                    'id' => $item->id,
                    'master_product_id' => $item->master_product_id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'uom' => $item->uom,
                    'qty' => (float) $item->qty,
                    'unit_price' => (float) $item->unit_price,
                    'discount_percent' => (float) $item->discount_percent,
                    'line_total' => (float) $item->line_total,
                    'multiplier' => (float) $item->multiplier,
                    'price_operation' => $item->price_operation,
                    'base_qty_used' => (int) $item->base_qty_used,
                ]),
                'requires_high_authorization' => ! (bool) Auth::user()?->hasRole('admin'),
            ],
            'payment_methods' => $this->posPaymentMethodsPayload(),
        ]);
    }

    public function updatePosTransactionPaymentMethod(Request $request, PosSale $posSale): RedirectResponse
    {
        $this->authorizeHighPrivilege($request);

        $validated = $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
        ]);

        $posSale->update([
            'payment_method_id' => (int) $validated['payment_method_id'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Metode pembayaran transaksi berhasil diperbarui.']);
    }

    public function refundPosTransaction(PosSale $posSale): RedirectResponse
    {
        $this->authorizeHighPrivilege(request());

        if ($posSale->status === 'refunded') {
            return back()->with('flash', ['type' => 'info', 'message' => 'Transaksi sudah berstatus refund.']);
        }

        DB::transaction(function () use ($posSale): void {
            $posSale->load('items');
            $posWarehouseId = $this->resolvePosWarehouseId();
            foreach ($posSale->items as $item) {
                if (! $item->master_product_id) {
                    continue;
                }
                $product = MasterProduct::query()->lockForUpdate()->find($item->master_product_id);
                if (! $product) {
                    continue;
                }
                $product->increment('stock', (int) $item->base_qty_used);
                $product->decrement('total_sold', min((int) $item->base_qty_used, (int) $product->total_sold));

                if ($posWarehouseId) {
                    $warehouseRow = MasterProductWarehouseStock::query()->firstOrCreate(
                        ['master_product_id' => $product->id, 'warehouse_id' => $posWarehouseId],
                        ['qty' => 0, 'reserved_qty' => 0]
                    );
                    $warehouseRow->increment('qty', (int) $item->base_qty_used);
                }

                ProductStockMovement::query()->create([
                    'master_product_id' => $product->id,
                    'warehouse_id' => $posWarehouseId,
                    'movement_date' => now()->toDateString(),
                    'movement_type' => 'pos_refund_in',
                    'qty' => (int) $item->base_qty_used,
                    'note' => 'Refund POS '.$posSale->number,
                ]);
            }

            $posSale->update([
                'status' => 'refunded',
                'note' => trim(($posSale->note ? $posSale->note.' | ' : '').'Refund '.now()->format('Y-m-d H:i')),
            ]);

            $coa = app(CoaSettingService::class);
            $cashAccount = $coa->resolveAccountByKey('pos_sale_cash_account', '1001');
            $revenueAccount = $coa->resolveAccountByKey('pos_sale_revenue_account', '4002');
            $additionalAccount = $coa->resolveAccountByKey('pos_sale_additional_income_account', '4004');
            $adminFee = max((float) ($posSale->sales_channel_admin_fee ?? 0), 0);
            $channelAdminExpenseAccount = $adminFee > 0 ? $coa->resolveAccountByKey('pos_sale_sales_channel_admin_expense', '5016') : null;
            $channelAdminPayableAccount = $adminFee > 0 ? $coa->resolveAccountByKey('pos_sale_sales_channel_admin_payable', '2090') : null;

            $netSales = max(((float) $posSale->gross_total) - ((float) $posSale->discount_total), 0);
            $additionalFeeAdd = max((float) ($posSale->additional_fee ?? 0), 0);
            $grandTotal = max((float) $posSale->grand_total, 0);

            $lines = $this->buildPosSaleCashReceiptRefundJournalLines(
                grandTotal: $grandTotal,
                netSales: $netSales,
                additionalFeeAddToTotal: $additionalFeeAdd,
                salesChannelAdminFee: $adminFee,
                cashAccount: $cashAccount,
                revenueAccount: $revenueAccount,
                additionalRevenueAccount: $additionalAccount,
                channelAdminExpenseAccount: $channelAdminExpenseAccount,
                channelAdminPayableAccount: $channelAdminPayableAccount,
            );

            $this->glPostingService->post(
                ErpCompanyResolver::resolveForGlPosting(request()),
                sourceModule: 'pos_sale_refund',
                sourceReference: $posSale->number,
                description: 'Refund POS '.$posSale->number,
                entryDate: now()->toDateString(),
                lines: $lines,
            );
        });

        return back()->with('flash', ['type' => 'warning', 'message' => 'Transaksi berhasil di-refund dan stok dikembalikan.']);
    }

    public function reopenPosTransaction(PosSale $posSale): RedirectResponse
    {
        $this->authorizeHighPrivilege(request());

        DB::transaction(function () use ($posSale): void {
            $posSale->load('items');
            $posWarehouseId = $this->resolvePosWarehouseId();
            if ($posSale->status === 'refunded') {
                foreach ($posSale->items as $item) {
                    if (! $item->master_product_id) {
                        continue;
                    }
                    $product = MasterProduct::query()->lockForUpdate()->find($item->master_product_id);
                    if (! $product) {
                        continue;
                    }
                    if ((int) $product->stock < (int) $item->base_qty_used) {
                        throw ValidationException::withMessages([
                            'reopen' => 'Stok tidak cukup untuk reopen transaksi ini.',
                        ]);
                    }
                    $product->decrement('stock', (int) $item->base_qty_used);
                    $product->increment('total_sold', (int) $item->base_qty_used);

                    if ($posWarehouseId) {
                        $warehouseRow = MasterProductWarehouseStock::query()->firstOrCreate(
                            ['master_product_id' => $product->id, 'warehouse_id' => $posWarehouseId],
                            ['qty' => 0, 'reserved_qty' => 0]
                        );
                        if ((float) $warehouseRow->qty < (int) $item->base_qty_used) {
                            throw ValidationException::withMessages([
                                'reopen' => "Stok warehouse untuk {$product->name} tidak cukup.",
                            ]);
                        }
                        $warehouseRow->decrement('qty', (int) $item->base_qty_used);
                    }

                    ProductStockMovement::query()->create([
                        'master_product_id' => $product->id,
                        'warehouse_id' => $posWarehouseId,
                        'movement_date' => now()->toDateString(),
                        'movement_type' => 'pos_reopen_out',
                        'qty' => (int) $item->base_qty_used,
                        'note' => 'Reopen POS '.$posSale->number,
                    ]);
                }
            }

            $posSale->update([
                'status' => 'reopened',
                'note' => trim(($posSale->note ? $posSale->note.' | ' : '').'Reopen '.now()->format('Y-m-d H:i')),
            ]);

            $coa = app(CoaSettingService::class);
            $cashAccount = $coa->resolveAccountByKey('pos_sale_cash_account', '1001');
            $revenueAccount = $coa->resolveAccountByKey('pos_sale_revenue_account', '4002');
            $additionalAccount = $coa->resolveAccountByKey('pos_sale_additional_income_account', '4004');
            $adminFee = max((float) ($posSale->sales_channel_admin_fee ?? 0), 0);
            $channelAdminExpenseAccount = $adminFee > 0 ? $coa->resolveAccountByKey('pos_sale_sales_channel_admin_expense', '5016') : null;
            $channelAdminPayableAccount = $adminFee > 0 ? $coa->resolveAccountByKey('pos_sale_sales_channel_admin_payable', '2090') : null;

            $netSales = max(((float) $posSale->gross_total) - ((float) $posSale->discount_total), 0);
            $additionalFeeAdd = max((float) ($posSale->additional_fee ?? 0), 0);
            $grandTotal = max((float) $posSale->grand_total, 0);

            $lines = $this->buildPosSaleCashReceiptJournalLines(
                grandTotal: $grandTotal,
                netSales: $netSales,
                additionalFeeAddToTotal: $additionalFeeAdd,
                salesChannelAdminFee: $adminFee,
                cashAccount: $cashAccount,
                revenueAccount: $revenueAccount,
                additionalRevenueAccount: $additionalAccount,
                channelAdminExpenseAccount: $channelAdminExpenseAccount,
                channelAdminPayableAccount: $channelAdminPayableAccount,
            );

            $this->glPostingService->post(
                ErpCompanyResolver::resolveForGlPosting(request()),
                sourceModule: 'pos_sale_reopen',
                sourceReference: $posSale->number,
                description: 'Reopen POS '.$posSale->number,
                entryDate: now()->toDateString(),
                lines: $lines,
            );
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Transaksi berhasil di-reopen.']);
    }

    public function checkoutPos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sales_channel' => ['required', 'string', Rule::in(array_column($this->priceChannels(), 'key'))],
            'marketplace_order_code' => ['nullable', 'string', 'max:100', 'required_if:sales_channel,marketplace'],
            'payment_method_id' => 'required|exists:payment_methods,id',
            'cash_paid' => 'nullable|numeric|min:0',
            'additional_charges' => 'nullable|array',
            'additional_charges.*.name' => 'required|string|max:120',
            'additional_charges.*.amount' => 'required|numeric|min:0.01',
            'additional_charges.*.kind' => 'nullable|string|in:add_to_total,journal_admin',
            'items' => 'required|array|min:1',
            'items.*.master_product_id' => 'required|integer|exists:master_products,id',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.multiplier' => 'nullable|numeric|gt:0',
            'items.*.price_operation' => 'nullable|in:multiply,divide',
            'items.*.sku' => 'nullable|string|max:100',
            'items.*.uom' => 'nullable|string|max:20',
        ]);

        $paymentMethod = PaymentMethod::query()
            ->with('salesChannelAssignments')
            ->findOrFail((int) $validated['payment_method_id']);
        $salesChannel = $validated['sales_channel'];

        if (! $paymentMethod->isAvailableForSalesChannel($salesChannel)) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Metode pembayaran tidak tersedia untuk sales channel ini.',
            ]);
        }
        $marketplaceOrderCode = $salesChannel === 'marketplace'
            ? trim((string) ($validated['marketplace_order_code'] ?? ''))
            : null;
        $items = collect($validated['items'])
            ->map(function (array $item) use ($salesChannel): array {
                $item['unit_price'] = $this->resolveCheckoutUnitPrice($item, $salesChannel);

                return $item;
            })
            ->all();
        $additionalCharges = collect($validated['additional_charges'] ?? [])
            ->map(function (array $charge): array {
                $kind = $charge['kind'] ?? 'add_to_total';
                if (! in_array($kind, ['add_to_total', 'journal_admin'], true)) {
                    $kind = 'add_to_total';
                }

                return [
                    'charge_name' => trim((string) $charge['name']),
                    'amount' => (float) $charge['amount'],
                    'kind' => $kind,
                ];
            })
            ->filter(fn (array $charge) => $charge['charge_name'] !== '' && $charge['amount'] > 0)
            ->values();
        $additionalFeeAdd = (float) $additionalCharges->where('kind', 'add_to_total')->sum('amount');
        $adminFee = (float) $additionalCharges->where('kind', 'journal_admin')->sum('amount');

        $grossTotal = collect($items)->sum(fn ($item) => (float) $item['unit_price'] * (float) $item['qty']);
        $discountTotal = collect($items)->sum(function ($item): float {
            $gross = (float) $item['unit_price'] * (float) $item['qty'];

            return $gross * (((float) ($item['discount_percent'] ?? 0)) / 100);
        });
        $netSalesTotal = max($grossTotal - $discountTotal, 0);
        if ($adminFee > $netSalesTotal) {
            throw ValidationException::withMessages([
                'additional_charges' => 'Total biaya admin channel tidak boleh melebihi nilai penjualan bersih.',
            ]);
        }
        $grandTotal = max($netSalesTotal + $additionalFeeAdd, 0);
        $cashPaid = (float) ($validated['cash_paid'] ?? 0);

        if ($paymentMethod->code === 'cash' && $cashPaid < $grandTotal) {
            throw ValidationException::withMessages([
                'cash_paid' => 'Nominal bayar kurang dari grand total.',
            ]);
        }

        $checkoutResult = DB::transaction(function () use ($items, $grossTotal, $discountTotal, $additionalCharges, $additionalFeeAdd, $adminFee, $grandTotal, $cashPaid, $paymentMethod, $salesChannel, $marketplaceOrderCode, $request): array {
            $transactionNumber = $this->documentNumberService->next('sales', 'pos_sale', [
                'prefix' => 'POS',
                'padding_length' => 6,
            ]);

            $sale = PosSale::query()->create([
                'number' => $transactionNumber,
                'sales_channel' => $salesChannel,
                'marketplace_order_code' => $marketplaceOrderCode,
                'payment_method_id' => $paymentMethod->id,
                'gross_total' => $grossTotal,
                'discount_total' => $discountTotal,
                'additional_fee' => $additionalFeeAdd,
                'sales_channel_admin_fee' => $adminFee,
                'grand_total' => $grandTotal,
                'cash_paid' => $cashPaid,
                'change_amount' => max($cashPaid - $grandTotal, 0),
                'status' => 'paid',
                'sold_at' => now(),
                'sold_by' => Auth::id(),
            ]);

            $saleItemsPayload = [];
            $posWarehouseId = $this->resolvePosWarehouseId();
            foreach ($items as $item) {
                $product = MasterProduct::query()->lockForUpdate()->findOrFail((int) $item['master_product_id']);
                $multiplier = max((float) ($item['multiplier'] ?? 1), 0.0001);
                $operation = $item['price_operation'] ?? 'multiply';
                $qty = (float) $item['qty'];

                $requiredBaseQty = $operation === 'divide'
                    ? (int) ceil($qty / $multiplier)
                    : (int) ceil($qty * $multiplier);

                if ($requiredBaseQty <= 0) {
                    $requiredBaseQty = 1;
                }

                if ((int) $product->stock < $requiredBaseQty) {
                    throw ValidationException::withMessages([
                        'stock' => "Stok {$product->name} tidak mencukupi untuk transaksi ini.",
                    ]);
                }

                $product->decrement('stock', $requiredBaseQty);
                $product->increment('total_sold', $requiredBaseQty);

                if ($posWarehouseId) {
                    $warehouseRow = MasterProductWarehouseStock::query()->firstOrCreate(
                        ['master_product_id' => $product->id, 'warehouse_id' => $posWarehouseId],
                        ['qty' => $product->stock + $requiredBaseQty, 'reserved_qty' => 0]
                    );
                    if ((float) $warehouseRow->qty < $requiredBaseQty) {
                        throw ValidationException::withMessages([
                            'stock' => "Stok warehouse untuk {$product->name} tidak mencukupi.",
                        ]);
                    }
                    $warehouseRow->decrement('qty', $requiredBaseQty);
                }

                ProductStockMovement::query()->create([
                    'master_product_id' => $product->id,
                    'warehouse_id' => $posWarehouseId,
                    'movement_date' => now()->toDateString(),
                    'movement_type' => 'pos_sale_out',
                    'qty' => $requiredBaseQty,
                    'note' => 'POS '.$transactionNumber,
                ]);

                $gross = (float) $item['unit_price'] * (float) $item['qty'];
                $discount = $gross * (((float) ($item['discount_percent'] ?? 0)) / 100);
                $lineTotal = max($gross - $discount, 0);
                $saleItemsPayload[] = [
                    'master_product_id' => (int) $item['master_product_id'],
                    'sku' => $item['sku'] ?? $product->sku,
                    'product_name' => $product->name,
                    'uom' => $item['uom'] ?? $product->uom,
                    'qty' => (float) $item['qty'],
                    'unit_price' => (float) $item['unit_price'],
                    'discount_percent' => (float) ($item['discount_percent'] ?? 0),
                    'line_total' => $lineTotal,
                    'multiplier' => $multiplier,
                    'price_operation' => $operation,
                    'base_qty_used' => $requiredBaseQty,
                ];
            }

            $sale->items()->createMany($saleItemsPayload);
            if ($additionalCharges->isNotEmpty()) {
                $sale->additionalCharges()->createMany($additionalCharges->all());
            }

            $coa = app(CoaSettingService::class);
            $cashAccount = $coa->resolveAccountByKey('pos_sale_cash_account', '1001');
            $revenueAccount = $coa->resolveAccountByKey('pos_sale_revenue_account', '4002');
            $additionalAccount = $coa->resolveAccountByKey('pos_sale_additional_income_account', '4004');
            $channelAdminExpenseAccount = $adminFee > 0 ? $coa->resolveAccountByKey('pos_sale_sales_channel_admin_expense', '5016') : null;
            $channelAdminPayableAccount = $adminFee > 0 ? $coa->resolveAccountByKey('pos_sale_sales_channel_admin_payable', '2090') : null;

            $netSales = max($grossTotal - $discountTotal, 0);
            $lines = $this->buildPosSaleCashReceiptJournalLines(
                grandTotal: $grandTotal,
                netSales: $netSales,
                additionalFeeAddToTotal: $additionalFeeAdd,
                salesChannelAdminFee: $adminFee,
                cashAccount: $cashAccount,
                revenueAccount: $revenueAccount,
                additionalRevenueAccount: $additionalAccount,
                channelAdminExpenseAccount: $channelAdminExpenseAccount,
                channelAdminPayableAccount: $channelAdminPayableAccount,
            );
            $this->glPostingService->post(
                ErpCompanyResolver::resolveForGlPosting($request),
                sourceModule: 'pos_sale',
                sourceReference: $transactionNumber,
                description: 'Penjualan POS '.$transactionNumber,
                entryDate: now()->toDateString(),
                lines: $lines,
            );

            return [
                'sale_id' => $sale->id,
                'transaction_number' => $transactionNumber,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i:s'),
                'items_count' => count($saleItemsPayload),
                'total_qty' => array_sum(array_map(fn ($item) => (float) $item['qty'], $saleItemsPayload)),
                'grand_total' => (float) $sale->grand_total,
                'payment_method' => $paymentMethod->name,
                'sales_channel' => $salesChannel,
                'sales_channel_label' => $this->priceChannelLabel($salesChannel),
                'marketplace_order_code' => $marketplaceOrderCode,
                'cashier' => Auth::user()?->name,
            ];
        });

        $directPrint = $this->tryDirectPrintPosReceipt(
            saleId: (int) ($checkoutResult['sale_id'] ?? 0),
            paymentMethodName: $paymentMethod->name,
        );

        return response()->json([
            'message' => 'Transaksi POS berhasil diproses.',
            'transaction_number' => $checkoutResult['transaction_number'],
            'grand_total' => $grandTotal,
            'cash_paid' => $cashPaid,
            'change' => max($cashPaid - $grandTotal, 0),
            'payment_method_name' => $paymentMethod->name,
            'sales_channel' => $salesChannel,
            'sales_channel_label' => $this->priceChannelLabel($salesChannel),
            'marketplace_order_code' => $marketplaceOrderCode,
            'transaction' => $checkoutResult,
            'direct_print' => $directPrint,
        ]);
    }

    public function printPosReceipt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_number' => 'required|string|max:30',
        ]);

        $sale = PosSale::query()
            ->with('paymentMethod:id,name')
            ->where('number', trim($validated['transaction_number']))
            ->first();

        if (! $sale) {
            throw ValidationException::withMessages([
                'transaction_number' => 'Transaksi POS tidak ditemukan.',
            ]);
        }

        $directPrint = $this->tryDirectPrintPosReceipt(
            saleId: (int) $sale->id,
            paymentMethodName: (string) ($sale->paymentMethod?->name ?? '-'),
        );

        if (! ($directPrint['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'printer' => 'Printer thermal belum aktif atau host printer belum diatur di pengaturan.',
            ]);
        }

        if (! ($directPrint['printed'] ?? false)) {
            throw ValidationException::withMessages([
                'printer' => (string) ($directPrint['message'] ?? 'Gagal mengirim struk ke printer thermal.'),
            ]);
        }

        return response()->json([
            'message' => 'Struk berhasil dikirim ke printer thermal.',
            'transaction_number' => $sale->number,
        ]);
    }

    /**
     * @return array{enabled: bool, printed: bool, message?: string|null}
     */
    private function tryDirectPrintPosReceipt(int $saleId, string $paymentMethodName): array
    {
        if ($saleId <= 0) {
            return ['enabled' => false, 'printed' => false];
        }

        $setting = ErpSetting::query()->first();
        $enabled = (bool) ($setting?->thermal_printer_enabled ?? false);
        $host = trim((string) ($setting?->thermal_printer_host ?? ''));
        $port = (int) ($setting?->thermal_printer_port ?? 9100);
        $paper = (string) ($setting?->thermal_paper_width ?? '80');

        if (! $enabled || $host === '') {
            return ['enabled' => $enabled, 'printed' => false];
        }

        $sale = PosSale::query()
            ->with(['items', 'soldBy:id,name', 'additionalCharges'])
            ->find($saleId);

        if (! $sale) {
            return ['enabled' => $enabled, 'printed' => false, 'message' => 'Transaksi tidak ditemukan untuk dicetak.'];
        }

        $printer = new LanEscPosPrinter;
        $renderer = new ThermalPosReceiptRenderer;

        $template = [
            'header' => $setting?->thermal_pos_header_template,
            'item_line' => $setting?->thermal_pos_item_line_template,
            'footer' => $setting?->thermal_pos_footer_template,
        ];

        $paper = $printer->normalizePaperWidth($paper);
        $cols = $printer->paperColumnWidth($paper);

        $soldAt = $sale->sold_at ?? now();
        $data = new ThermalPosReceiptData(
            appName: (string) ($setting?->app_name ?: 'OCN ERP Suite'),
            transactionNumber: (string) $sale->number,
            date: $soldAt->format('Y-m-d'),
            time: $soldAt->format('H:i'),
            paymentMethod: $paymentMethodName,
            cashierName: (string) ($sale->soldBy?->name ?: '-'),
            grossTotal: number_format((float) $sale->gross_total, 0, ',', '.'),
            discountTotal: number_format((float) $sale->discount_total, 0, ',', '.'),
            grandTotal: number_format((float) $sale->grand_total, 0, ',', '.'),
            cashPaid: number_format((float) $sale->cash_paid, 0, ',', '.'),
            change: number_format((float) $sale->change_amount, 0, ',', '.'),
            lines: $sale->items->map(fn ($row) => [
                'sku' => (string) $row->sku,
                'name' => (string) $row->product_name,
                'qty' => (float) $row->qty,
                'unit_price' => number_format((float) $row->unit_price, 0, ',', '.'),
                'line_total' => number_format((float) $row->line_total, 0, ',', '.'),
                'uom' => (string) ($row->uom ?? ''),
                'discount_percent' => (float) ($row->discount_percent ?? 0),
            ])->all(),
            additionalFee: number_format((float) ($sale->additional_fee ?? 0), 0, ',', '.'),
            additionalCharges: $sale->additionalCharges
                ->filter(fn ($charge) => ($charge->kind ?? 'add_to_total') === 'add_to_total')
                ->map(fn ($charge) => [
                    'name' => (string) $charge->charge_name,
                    'amount' => number_format((float) $charge->amount, 0, ',', '.'),
                ])->all(),
        );

        $layout = [
            'header_align' => $setting?->thermal_pos_header_align ?? 'center',
            'item_align' => $setting?->thermal_pos_item_align ?? 'left',
            'footer_align' => $setting?->thermal_pos_footer_align ?? 'right',
            'section_gap' => (int) ($setting?->thermal_pos_section_gap ?? 0),
            'header_emphasis' => (bool) ($setting?->thermal_pos_header_emphasis ?? true),
        ];

        $marginMm = (float) ($setting?->thermal_pos_margin_left_mm ?? 0);
        $marginChars = ThermalPosReceiptRenderer::marginCharsFromMm($marginMm, $paper, $cols);
        $layout['content_cols'] = max(8, $cols - $marginChars);

        $segments = $renderer->buildReceiptSegments($template, $data, $paper, $cols, $layout);

        try {
            $printer->sendStructuredReceipt($host, $port, $segments, $paper, $marginChars);

            return ['enabled' => true, 'printed' => true];
        } catch (\RuntimeException $e) {
            return ['enabled' => true, 'printed' => false, 'message' => $e->getMessage()];
        }
    }

    private function resolvePosWarehouseId(): ?int
    {
        return Warehouse::query()->where('is_active', true)->orderBy('id')->value('id');
    }

    private function productChannelPrices(MasterProduct $product): array
    {
        $configured = $product->channelPrices
            ->keyBy('sales_channel')
            ->map(fn ($price) => (float) $price->selling_price);

        return collect($this->priceChannels())
            ->mapWithKeys(fn ($channel) => [
                $channel['key'] => (float) ($configured[$channel['key']] ?? $product->selling_price),
            ])
            ->all();
    }

    private function mappedChannelPrices(array $baseChannelPrices, float $manualPrice, float $multiplier, string $operation, bool $useAutoPrice): array
    {
        if (! $useAutoPrice) {
            return collect($this->priceChannels())
                ->mapWithKeys(fn ($channel) => [$channel['key'] => $manualPrice])
                ->all();
        }

        return collect($baseChannelPrices)
            ->map(fn ($price) => $this->computeMappedPrice((float) $price, $multiplier, $operation))
            ->all();
    }

    private function computeMappedPrice(float $basePrice, float $multiplier, string $operation): float
    {
        if ($operation === 'divide') {
            return round($basePrice / max($multiplier, 0.0001), 2);
        }

        return round($basePrice * $multiplier, 2);
    }

    private function resolveCheckoutUnitPrice(array $item, string $salesChannel): float
    {
        $product = MasterProduct::query()
            ->with([
                'channelPrices' => fn ($q) => $q->where('status', 'active'),
                'uomMappings' => fn ($q) => $q->where('status', 'active'),
            ])
            ->findOrFail((int) $item['master_product_id']);

        $basePrice = (float) ($product->channelPrices->firstWhere('sales_channel', $salesChannel)?->selling_price ?? $product->selling_price);
        $uom = (string) ($item['uom'] ?? $product->uom);
        if ($uom === (string) $product->uom) {
            return $basePrice;
        }

        $mapping = $product->uomMappings->firstWhere('uom_code', $uom);
        if (! $mapping) {
            return $basePrice;
        }

        if (! (bool) $mapping->use_auto_price) {
            return (float) $mapping->selling_price;
        }

        return $this->computeMappedPrice($basePrice, (float) $mapping->multiplier, $mapping->price_operation ?: 'multiply');
    }

    /**
     * @return array<int, array{id: int, code: string, name: string, sales_channels: list<string>}>
     */
    private function posPaymentMethodsPayload(): array
    {
        return PaymentMethod::query()
            ->with('salesChannelAssignments')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'code' => $method->code,
                'name' => $method->name,
                'sales_channels' => $method->salesChannelsList(),
            ])
            ->values()
            ->all();
    }

    private function priceChannelLabel(string $key): string
    {
        return collect($this->priceChannels())->firstWhere('key', $key)['label'] ?? strtoupper($key);
    }

    private function priceChannels(): array
    {
        return [
            ['key' => 'retail', 'label' => 'Retail'],
            ['key' => 'grosir', 'label' => 'Grosir'],
            ['key' => 'reseller', 'label' => 'Reseller'],
            ['key' => 'marketplace', 'label' => 'Marketplace'],
            ['key' => 'online', 'label' => 'Online'],
        ];
    }

    /**
     * Jurnal POS saat kas masuk: grand total = penjualan bersih + biaya lain yang ditagih.
     * Biaya admin channel: debit beban + kredit hutang estimasi (tidak mengubah grand total / kas).
     *
     * @return array<int, array{account_id: int, debit: float, credit: float}>
     */
    private function buildPosSaleCashReceiptJournalLines(
        float $grandTotal,
        float $netSales,
        float $additionalFeeAddToTotal,
        float $salesChannelAdminFee,
        Account $cashAccount,
        Account $revenueAccount,
        Account $additionalRevenueAccount,
        ?Account $channelAdminExpenseAccount,
        ?Account $channelAdminPayableAccount,
    ): array {
        $netSales = max($netSales, 0.0);
        $admin = max($salesChannelAdminFee, 0.0);
        $add = max($additionalFeeAddToTotal, 0.0);
        $grand = max($grandTotal, 0.0);

        if ($admin > $netSales + 1e-9) {
            throw ValidationException::withMessages([
                'additional_charges' => 'Total biaya admin channel tidak boleh melebihi nilai penjualan bersih.',
            ]);
        }

        $lines = [
            ['account_id' => $cashAccount->id, 'debit' => $grand, 'credit' => 0.0],
            ['account_id' => $revenueAccount->id, 'debit' => 0.0, 'credit' => $netSales],
        ];

        if ($add > 0) {
            $lines[] = ['account_id' => $additionalRevenueAccount->id, 'debit' => 0.0, 'credit' => $add];
        }

        if ($admin > 0) {
            if ($channelAdminExpenseAccount === null || $channelAdminPayableAccount === null) {
                throw new \InvalidArgumentException('Akun beban / hutang admin channel wajib ada jika ada biaya admin.');
            }
            $lines[] = ['account_id' => $channelAdminExpenseAccount->id, 'debit' => $admin, 'credit' => 0.0];
            $lines[] = ['account_id' => $channelAdminPayableAccount->id, 'debit' => 0.0, 'credit' => $admin];
        }

        return $lines;
    }

    /**
     * Kebalikan dari {@see buildPosSaleCashReceiptJournalLines} untuk refund POS.
     *
     * @return array<int, array{account_id: int, debit: float, credit: float}>
     */
    private function buildPosSaleCashReceiptRefundJournalLines(
        float $grandTotal,
        float $netSales,
        float $additionalFeeAddToTotal,
        float $salesChannelAdminFee,
        Account $cashAccount,
        Account $revenueAccount,
        Account $additionalRevenueAccount,
        ?Account $channelAdminExpenseAccount,
        ?Account $channelAdminPayableAccount,
    ): array {
        $netSales = max($netSales, 0.0);
        $admin = max($salesChannelAdminFee, 0.0);
        $add = max($additionalFeeAddToTotal, 0.0);
        $grand = max($grandTotal, 0.0);

        $lines = [
            ['account_id' => $cashAccount->id, 'debit' => 0.0, 'credit' => $grand],
            ['account_id' => $revenueAccount->id, 'debit' => $netSales, 'credit' => 0.0],
        ];

        if ($add > 0) {
            $lines[] = ['account_id' => $additionalRevenueAccount->id, 'debit' => $add, 'credit' => 0.0];
        }

        if ($admin > 0) {
            if ($channelAdminExpenseAccount === null || $channelAdminPayableAccount === null) {
                throw new \InvalidArgumentException('Akun beban / hutang admin channel wajib ada jika ada biaya admin.');
            }
            $lines[] = ['account_id' => $channelAdminExpenseAccount->id, 'debit' => 0.0, 'credit' => $admin];
            $lines[] = ['account_id' => $channelAdminPayableAccount->id, 'debit' => $admin, 'credit' => 0.0];
        }

        return $lines;
    }

    private function authorizeHighPrivilege(Request $request): void
    {
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->hasRole('admin')) {
            return;
        }

        $validated = $request->validate([
            'authorization_email' => 'required|email',
            'authorization_password' => 'required|string|min:6',
        ]);

        $adminUser = User::query()->where('email', $validated['authorization_email'])->first();
        if (! $adminUser || ! $adminUser->hasRole('admin') || ! Hash::check($validated['authorization_password'], $adminUser->password)) {
            throw ValidationException::withMessages([
                'authorization' => 'Otorisasi admin tidak valid.',
            ]);
        }
    }

    public function projectInvoices(Request $request): Response
    {
        $invoices = Project::query()
            ->with(['convertedBudget.items', 'materials'])
            ->withSum('cashIns as paid_amount', 'amount')
            ->where('status', 'selesai')
            ->latest('finished_at')
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(function (Project $project) {
                $project->invoice_number = $this->ensureProjectInvoiceNumber($project);

                return $this->mapProjectInvoice($project);
            });

        return Inertia::render('ERP/Sales/ProjectInvoices', [
            'invoices' => $invoices,
            'filters' => $this->filtersWithPerPage($request, []),
        ]);
    }

    public function projectInvoiceShow(Project $project): Response
    {
        abort_unless($project->status === 'selesai', 404);

        $project->load(['payments', 'cashIns.creator', 'cashIns.paymentMethod', 'cashIns.cashAccount', 'materials.product', 'convertedBudget.items', 'projectTypeDefinition']);
        $project->loadSum('cashIns as paid_amount', 'amount');
        $project->invoice_number = $this->ensureProjectInvoiceNumber($project);

        return Inertia::render('ERP/Sales/ProjectInvoiceShow', [
            'invoice' => $this->mapProjectInvoice($project) + [
                'client_contact' => $project->client_contact,
                'project_type' => $project->project_type,
                'project_type_label' => $project->projectTypeLabel(),
                'started_at' => $project->started_at?->format('Y-m-d'),
                'finished_at' => $project->finished_at?->format('Y-m-d'),
                'description' => $project->description,
                'payments' => $project->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'term_number' => $payment->term_number,
                    'percentage' => (float) $payment->percentage,
                    'amount' => (float) $payment->amount,
                    'paid_at' => $payment->paid_at?->format('Y-m-d'),
                    'note' => $payment->note,
                ]),
                'cash_ins' => $project->cashIns
                    ->sortByDesc('date')
                    ->values()
                    ->map(fn (CashIn $cashIn) => [
                        'id' => $cashIn->id,
                        'amount' => (float) $cashIn->amount,
                        'date' => $cashIn->date?->format('Y-m-d'),
                        'category' => $cashIn->category,
                        'payment_method_id' => $cashIn->payment_method_id,
                        'payment_method_name' => $cashIn->paymentMethod?->name,
                        'cash_account_id' => $cashIn->cash_account_id,
                        'cash_account_name' => $cashIn->cashAccount?->displayLabel(),
                        'note' => $cashIn->note,
                        'creator_name' => $cashIn->creator?->name,
                    ]),
                'line_items' => $project->resolveInvoiceLineItems()->values()->all(),
            ],
            'paymentMethods' => PaymentMethod::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'cashAccounts' => Account::cashBankOptions(),
        ]);
    }

    public function storeProjectInvoicePayment(Request $request, Project $project)
    {
        abort_unless($project->status === 'selesai', 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'note' => 'nullable|string|max:1000',
        ]);

        $paidAmount = (float) $project->cashIns()->sum('amount');
        $invoiceAmount = $project->resolveInvoiceAmount();
        $remaining = max($invoiceAmount - $paidAmount, 0);
        if ((float) $validated['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah pembayaran melebihi sisa tagihan: Rp '.number_format($remaining, 0, ',', '.'),
            ]);
        }

        $coa = app(CoaSettingService::class);
        $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);
        $revenueAccount = $coa->resolveAccountByKey('project_invoice_revenue_account', '4003');

        $validated['project_id'] = $project->id;
        $validated['category'] = 'pendapatan_project';
        $validated['created_by'] = Auth::id();
        $validated['document_status'] = DocumentStatus::Posted->value;
        $validated['approved_at'] = now();
        $validated['approved_by'] = Auth::id();
        $validated['posted_at'] = now();
        $validated['posted_by'] = Auth::id();

        $cashIn = CashIn::query()->create($validated);

        $entry = $this->glPostingService->post(
            ErpCompanyResolver::resolveForGlPosting($request),
            sourceModule: 'project_invoice_payment',
            sourceReference: (string) $cashIn->id,
            description: 'Pembayaran invoice project '.$project->name,
            entryDate: $validated['date'],
            lines: [
                ['account_id' => $cashAccount->id, 'debit' => $validated['amount'], 'credit' => 0],
                ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $validated['amount']],
            ],
        );

        $cashIn->update(['journal_entry_id' => $entry->id]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Pembayaran invoice berhasil dicatat.']);
    }

    public function updateProjectInvoicePayment(Request $request, Project $project, CashIn $cashIn)
    {
        abort_unless($project->status === 'selesai' && $cashIn->project_id === $project->id, 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'note' => 'nullable|string|max:1000',
        ]);

        $totalPaidOther = (float) $project->cashIns()->whereKeyNot($cashIn->id)->sum('amount');
        $remaining = max($project->resolveInvoiceAmount() - $totalPaidOther, 0);
        if ((float) $validated['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah pembayaran melebihi sisa tagihan: Rp '.number_format($remaining, 0, ',', '.'),
            ]);
        }

        $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);

        DB::transaction(function () use ($cashIn, $validated, $project, $cashAccount): void {
            $cashIn->update([
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'payment_method_id' => $validated['payment_method_id'],
                'cash_account_id' => $cashAccount->id,
                'note' => $validated['note'] ?? null,
            ]);

            if ($cashIn->journal_entry_id) {
                $entry = JournalEntry::query()->with('lines')->find($cashIn->journal_entry_id);
                if ($entry) {
                    $entry->update([
                        'entry_date' => $validated['date'],
                        'description' => 'Pembayaran invoice project '.$project->name.' (updated)',
                    ]);

                    foreach ($entry->lines as $line) {
                        if ((float) $line->debit > 0) {
                            $line->update([
                                'account_id' => $cashAccount->id,
                                'debit' => $validated['amount'],
                                'credit' => 0,
                            ]);
                        } else {
                            $line->update(['debit' => 0, 'credit' => $validated['amount']]);
                        }
                    }
                }
            }
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Pembayaran invoice berhasil diperbarui.']);
    }

    public function downloadProjectInvoice(Project $project)
    {
        abort_unless($project->status === 'selesai', 404);

        $project->invoice_number = $this->ensureProjectInvoiceNumber($project);
        $project->load(['payments', 'cashIns', 'materials.product', 'convertedBudget.items']);
        $project->loadSum('cashIns as paid_amount', 'amount');

        $lineItems = $project->resolveInvoiceLineItems();

        $pdf = Pdf::loadView('pdf.project-invoice', [
            'project' => $project,
            'invoice' => $this->mapProjectInvoice($project),
            'lineItems' => $lineItems,
            'lineItemsSubtotal' => $lineItems->sum('subtotal'),
            'brand' => $this->pdfBrand(),
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->download($this->invoiceNumber($project).'.pdf');
    }

    public function downloadProjectSalesNote(Project $project)
    {
        abort_unless($project->status === 'selesai', 404);

        $project->invoice_number = $this->ensureProjectInvoiceNumber($project);
        $project->load(['materials.product', 'convertedBudget.items']);
        $project->loadSum('cashIns as paid_amount', 'amount');

        $invoice = $this->mapProjectInvoice($project);
        $items = $project->resolveInvoiceLineItems();

        $pdf = Pdf::loadView('pdf.project-sales-note', [
            'project' => $project,
            'invoice' => $invoice,
            'items' => $items,
            'itemsSubtotal' => $items->sum('subtotal'),
            'brand' => $this->pdfBrand(),
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->download('NOTA-'.$this->invoiceNumber($project).'.pdf');
    }

    public function downloadProjectReceipt(Project $project, CashIn $cashIn)
    {
        abort_unless($project->status === 'selesai' && $cashIn->project_id === $project->id, 404);

        $project->invoice_number = $this->ensureProjectInvoiceNumber($project);
        $project->loadSum('cashIns as paid_amount', 'amount');
        $cashIn->load('paymentMethod');

        $pdf = Pdf::loadView('pdf.project-receipt', [
            'project' => $project,
            'cashIn' => $cashIn,
            'invoice' => $this->mapProjectInvoice($project),
            'brand' => $this->pdfBrand(),
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->download('KW-'.$this->invoiceNumber($project).'-'.($cashIn->date?->format('Ymd') ?? now()->format('Ymd')).'.pdf');
    }

    private function pdfBrand(): array
    {
        $setting = ErpSetting::query()->first();
        $logoDataUri = null;

        if ($setting?->app_logo_path && Storage::disk('public')->exists($setting->app_logo_path)) {
            $path = Storage::disk('public')->path($setting->app_logo_path);
            $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';
            $logoDataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
        }

        return [
            'name' => (string) ($setting?->app_name ?: 'OCN ERP Suite'),
            'tagline' => (string) ($setting?->app_tagline ?: 'Integrated Business Platform'),
            'logo_data_uri' => $logoDataUri,
        ];
    }

    private function mapProjectInvoice(Project $project): array
    {
        $paidAmount = (float) ($project->paid_amount ?? $project->cashIns()->sum('amount'));
        $amount = $project->resolveInvoiceAmount();
        $remaining = max($amount - $paidAmount, 0);

        return [
            'id' => $project->id,
            'number' => $project->invoice_number ?: $this->invoiceNumber($project),
            'project' => $project->name,
            'client' => $project->client_name,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remaining,
            'status' => $remaining <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
            'finished_at' => $project->finished_at?->format('Y-m-d'),
            'created_at' => $project->created_at?->format('Y-m-d'),
        ];
    }

    private function invoiceNumber(Project $project): string
    {
        return $project->invoice_number
            ?: ('INV-PRJ-'.($project->finished_at?->format('Ymd') ?? $project->created_at?->format('Ymd') ?? now()->format('Ymd')).'-'.strtoupper(substr(str_replace('-', '', (string) $project->getKey()), -6)));
    }

    private function ensureProjectInvoiceNumber(Project $project): string
    {
        if ($project->invoice_number) {
            return $project->invoice_number;
        }

        return DB::transaction(function () use ($project): string {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            if ($locked->invoice_number) {
                return $locked->invoice_number;
            }

            $nextNumber = $this->documentNumberService->next('sales', 'project_invoice', [
                'prefix' => 'INV-PRJ',
                'padding_length' => 6,
            ]);

            $locked->update([
                'invoice_number' => $nextNumber,
                'invoiced_at' => now(),
            ]);

            return $nextNumber;
        });
    }
}
