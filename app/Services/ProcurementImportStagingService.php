<?php

namespace App\Services;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Purchasing\Models\GoodsReceipt;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Shared\Enums\DocumentStatus;
use App\ERP\Shared\Services\DocumentNumberService;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProcurementImportStaging;
use App\Models\ProcurementImportStagingLine;
use App\Models\ProductStockMovement;
use App\Models\ProjectMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcurementImportStagingService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly GlPostingService $glPostingService,
    ) {}

    /**
     * @param  array{procurement_date: string, notes?: string|null, lines: array<int, array{id: int|string, vendor_id?: int|string|null, qty: int|float|string, unit_cost: int|float|string}>}  $payload
     */
    public function updateDraft(ProcurementImportStaging $staging, array $payload): ProcurementImportStaging
    {
        if ($staging->status === 'converted') {
            throw new RuntimeException('Staging yang sudah dikonversi tidak bisa diubah lagi.');
        }

        return DB::transaction(function () use ($staging, $payload): ProcurementImportStaging {
            $linePayloads = collect($payload['lines'] ?? [])->keyBy(fn (array $line) => (int) $line['id']);
            $staging->loadMissing('lines');

            foreach ($staging->lines as $line) {
                $linePayload = $linePayloads->get((int) $line->id);
                if (! is_array($linePayload)) {
                    throw new RuntimeException('Ada line staging yang tidak ikut terkirim saat penyimpanan.');
                }

                $qty = max((float) $linePayload['qty'], 0.01);
                $unitCost = max((float) $linePayload['unit_cost'], 0);
                $vendorId = $linePayload['vendor_id'] !== null && $linePayload['vendor_id'] !== ''
                    ? (int) $linePayload['vendor_id']
                    : null;

                $line->update([
                    'vendor_id' => $vendorId,
                    'qty' => number_format($qty, 2, '.', ''),
                    'unit_cost' => number_format($unitCost, 2, '.', ''),
                    'line_total' => number_format($qty * $unitCost, 2, '.', ''),
                    'status' => $vendorId ? 'ready' : 'draft',
                ]);
            }

            $staging->refresh()->load('lines');
            $allLinesAssigned = $staging->lines->every(fn (ProcurementImportStagingLine $line) => $line->vendor_id !== null);

            $staging->update([
                'procurement_date' => $payload['procurement_date'],
                'notes' => $payload['notes'] ?? null,
                'status' => $allLinesAssigned ? 'ready' : 'draft',
            ]);

            return $staging->fresh(['lines.product', 'lines.vendor', 'project', 'company', 'warehouse']);
        });
    }

    public function convertToPurchasingDocuments(ProcurementImportStaging $staging, int $performedByUserId): array
    {
        if ($staging->status === 'converted') {
            throw new RuntimeException('Staging ini sudah pernah dikonversi ke dokumen purchasing.');
        }

        $syncSummary = $this->reconcileSingleOpenStaging((string) $staging->id);
        if (($syncSummary['deleted_stagings'] ?? 0) > 0) {
            throw new RuntimeException('Semua line procurement pada staging ini sekarang bertipe jasa/service. Staging sudah dibersihkan dan tidak perlu dikonversi.');
        }

        return DB::transaction(function () use ($staging, $performedByUserId): array {
            $staging->loadMissing(['project', 'warehouse', 'lines.product', 'lines.vendor']);

            if ($staging->lines->isEmpty()) {
                throw new RuntimeException('Staging tidak memiliki line item untuk dikonversi.');
            }

            $missingVendor = $staging->lines->first(fn (ProcurementImportStagingLine $line) => ! $line->vendor_id);
            if ($missingVendor) {
                throw new RuntimeException('Semua line staging harus punya supplier sebelum dikonversi.');
            }

            $purchaseOrders = [];
            $goodsReceipts = [];

            $staging->lines
                ->groupBy(fn (ProcurementImportStagingLine $line) => (int) $line->vendor_id)
                ->each(function (Collection $vendorLines, int $vendorId) use ($staging, $performedByUserId, &$purchaseOrders, &$goodsReceipts): void {
                    $aggregatedLines = $vendorLines
                        ->groupBy(fn (ProcurementImportStagingLine $line) => (int) $line->master_product_id)
                        ->map(function (Collection $productLines): array {
                            $qty = (float) $productLines->sum(fn (ProcurementImportStagingLine $line) => (float) $line->qty);
                            $lineTotal = (float) $productLines->sum(fn (ProcurementImportStagingLine $line) => (float) $line->line_total);
                            $unitPrice = $qty > 0 ? $lineTotal / $qty : 0;

                            return [
                                'master_product_id' => (int) $productLines->first()->master_product_id,
                                'qty' => $qty,
                                'unit_price' => $unitPrice,
                                'line_total' => $lineTotal,
                            ];
                        })
                        ->values();

                    $po = PurchaseOrder::query()->create([
                        'number' => $this->documentNumberService->next('purchasing', 'purchase_order', [
                            'prefix' => 'PO',
                            'padding_length' => 6,
                        ]),
                        'vendor_id' => $vendorId,
                        'order_date' => $staging->procurement_date?->toDateString() ?? now()->toDateString(),
                        'eta_date' => $staging->procurement_date?->toDateString() ?? now()->toDateString(),
                        'total_amount' => number_format((float) $aggregatedLines->sum('line_total'), 2, '.', ''),
                        'status' => DocumentStatus::Approved,
                        'notes' => $this->purchaseOrderNotes($staging),
                        'approved_at' => now(),
                        'approved_by' => $performedByUserId,
                    ]);

                    foreach ($aggregatedLines as $line) {
                        $po->lines()->create([
                            'master_product_id' => $line['master_product_id'],
                            'qty' => number_format($line['qty'], 2, '.', ''),
                            'received_qty' => 0,
                            'unit_price' => number_format($line['unit_price'], 2, '.', ''),
                            'line_total' => number_format($line['line_total'], 2, '.', ''),
                        ]);
                    }

                    $grn = GoodsReceipt::query()->create([
                        'number' => $this->documentNumberService->next('purchasing', 'goods_receipt', [
                            'prefix' => 'GRN',
                            'padding_length' => 6,
                        ]),
                        'purchase_order_id' => $po->id,
                        'received_date' => $staging->procurement_date?->toDateString() ?? now()->toDateString(),
                        'warehouse_id' => $staging->warehouse_id,
                        'warehouse_name' => $staging->warehouse?->name ?? 'Warehouse',
                        'status' => DocumentStatus::Approved,
                    ]);

                    foreach ($aggregatedLines as $line) {
                        $grn->lines()->create([
                            'master_product_id' => $line['master_product_id'],
                            'qty_received' => number_format($line['qty'], 2, '.', ''),
                        ]);
                    }

                    $this->postGoodsReceiptFromLegacyConversion(
                        $grn->fresh(['purchaseOrder.lines', 'purchaseOrder.vendor', 'warehouse', 'lines.product']),
                        $performedByUserId,
                        (int) $staging->company_id,
                    );

                    $vendorLines->each(function (ProcurementImportStagingLine $line): void {
                        $line->update(['status' => 'converted']);
                    });

                    $purchaseOrders[] = [
                        'number' => $po->number,
                        'vendor_id' => $vendorId,
                    ];
                    $goodsReceipts[] = [
                        'number' => $grn->number,
                        'purchase_order_number' => $po->number,
                    ];
                });

            $staging->update([
                'status' => 'converted',
                'converted_at' => now(),
                'converted_by' => $performedByUserId,
                'conversion_summary' => [
                    'purchase_orders' => $purchaseOrders,
                    'goods_receipts' => $goodsReceipts,
                    'mode' => 'legacy_auto_posted',
                ],
            ]);

            return [
                'purchase_orders' => $purchaseOrders,
                'goods_receipts' => $goodsReceipts,
            ];
        });
    }

    public function reconcileOpenStagings(): array
    {
        $summary = [
            'checked_stagings' => 0,
            'updated_stagings' => 0,
            'deleted_stagings' => 0,
            'removed_service_lines' => 0,
            'refreshed_product_lines' => 0,
        ];

        ProcurementImportStaging::query()
            ->whereIn('status', ['draft', 'ready'])
            ->orderBy('created_at')
            ->chunkById(100, function (Collection $stagings) use (&$summary): void {
                foreach ($stagings as $staging) {
                    $result = $this->reconcileSingleOpenStaging((string) $staging->id);
                    $summary['checked_stagings']++;
                    $summary['updated_stagings'] += (int) ($result['updated_stagings'] ?? 0);
                    $summary['deleted_stagings'] += (int) ($result['deleted_stagings'] ?? 0);
                    $summary['removed_service_lines'] += (int) ($result['removed_service_lines'] ?? 0);
                    $summary['refreshed_product_lines'] += (int) ($result['refreshed_product_lines'] ?? 0);
                }
            });

        return $summary;
    }

    public function reconcileStagingsForImportKey(string $sourceImportKey): array
    {
        $summary = [
            'checked_stagings' => 0,
            'updated_stagings' => 0,
            'deleted_stagings' => 0,
            'removed_service_lines' => 0,
            'refreshed_product_lines' => 0,
        ];

        ProcurementImportStaging::query()
            ->where('source_import_key', $sourceImportKey)
            ->whereIn('status', ['draft', 'ready'])
            ->orderBy('created_at')
            ->chunkById(100, function (Collection $stagings) use (&$summary): void {
                foreach ($stagings as $staging) {
                    $result = $this->reconcileSingleOpenStaging((string) $staging->id);
                    $summary['checked_stagings']++;
                    $summary['updated_stagings'] += (int) ($result['updated_stagings'] ?? 0);
                    $summary['deleted_stagings'] += (int) ($result['deleted_stagings'] ?? 0);
                    $summary['removed_service_lines'] += (int) ($result['removed_service_lines'] ?? 0);
                    $summary['refreshed_product_lines'] += (int) ($result['refreshed_product_lines'] ?? 0);
                }
            });

        return $summary;
    }

    /**
     * @return array{updated_stagings:int, deleted_stagings:int, removed_service_lines:int, refreshed_product_lines:int}
     */
    private function reconcileSingleOpenStaging(string $stagingId): array
    {
        return DB::transaction(function () use ($stagingId): array {
            $staging = ProcurementImportStaging::query()
                ->with(['lines.product'])
                ->lockForUpdate()
                ->find($stagingId);

            if (! $staging || $staging->status === 'converted') {
                return [
                    'updated_stagings' => 0,
                    'deleted_stagings' => 0,
                    'removed_service_lines' => 0,
                    'refreshed_product_lines' => 0,
                ];
            }

            $removedServiceLines = 0;
            $refreshedProductLines = 0;

            foreach ($staging->lines as $line) {
                $product = $line->product;

                if ($product instanceof MasterProduct && ! $product->isStockTracked()) {
                    $line->delete();
                    $removedServiceLines++;

                    continue;
                }

                if (! $product instanceof MasterProduct) {
                    continue;
                }

                $updates = [];

                if ((string) $line->product_name !== (string) $product->name) {
                    $updates['product_name'] = $product->name;
                }

                if ((string) ($line->legacy_product_sku ?? '') !== (string) ($product->sku ?? '')) {
                    $updates['legacy_product_sku'] = $product->sku;
                }

                if ($updates !== []) {
                    $line->update($updates);
                    $refreshedProductLines++;
                }
            }

            $remainingLines = $staging->lines()->get();

            if ($remainingLines->isEmpty()) {
                $staging->delete();

                return [
                    'updated_stagings' => 0,
                    'deleted_stagings' => 1,
                    'removed_service_lines' => $removedServiceLines,
                    'refreshed_product_lines' => $refreshedProductLines,
                ];
            }

            $allLinesAssigned = $remainingLines->every(fn (ProcurementImportStagingLine $line) => $line->vendor_id !== null);
            $nextStatus = $allLinesAssigned ? 'ready' : 'draft';
            $updatedStagings = 0;

            if ($staging->status !== $nextStatus) {
                $staging->update(['status' => $nextStatus]);
                $updatedStagings = 1;
            }

            return [
                'updated_stagings' => $updatedStagings,
                'deleted_stagings' => 0,
                'removed_service_lines' => $removedServiceLines,
                'refreshed_product_lines' => $refreshedProductLines,
            ];
        });
    }

    private function purchaseOrderNotes(ProcurementImportStaging $staging): string
    {
        return trim(implode("\n", array_filter([
            'Converted from procurement import staging.',
            'Legacy project: '.$staging->legacy_project_number.' - '.$staging->legacy_project_name,
            'Import key: '.$staging->source_import_key,
            $staging->notes ?: null,
        ])));
    }

    private function postGoodsReceiptFromLegacyConversion(GoodsReceipt $receipt, int $performedByUserId, int $companyId): void
    {
        if ($receipt->status === DocumentStatus::Posted) {
            return;
        }

        $receipt->update([
            'status' => DocumentStatus::Posted,
            'posted_at' => now(),
            'posted_by' => $performedByUserId,
        ]);

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
                throw new RuntimeException('Qty GRN melebihi sisa PO untuk produk '.$product->name.'.');
            }

            $warehouseId = $receipt->warehouse_id;
            if ($warehouseId) {
                $row = MasterProductWarehouseStock::query()->firstOrCreate(
                    ['master_product_id' => $product->id, 'warehouse_id' => $warehouseId],
                    ['qty' => 0, 'reserved_qty' => 0]
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

            $product->increment('stock', (float) $line->qty_received);
            $poLine?->increment('received_qty', (float) $line->qty_received);

            ProductStockMovement::query()->create([
                'master_product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'movement_date' => $receipt->received_date->toDateString(),
                'movement_type' => 'purchase_receipt',
                'qty' => $line->qty_received,
                'note' => 'Legacy receipt '.$receipt->number,
            ]);
        }

        $inventoryAccount = Account::query()->where('code', '1201')->firstOrFail();
        $payableAccount = Account::query()->where('code', '2001')->firstOrFail();
        $amount = (float) $receipt->purchaseOrder->total_amount;

        $entry = $this->glPostingService->post(
            $companyId,
            sourceModule: 'purchasing',
            sourceReference: $receipt->number,
            description: 'Posting penerimaan barang legacy '.$receipt->number,
            entryDate: $receipt->received_date->toDateString(),
            lines: [
                ['account_id' => $inventoryAccount->id, 'debit' => $amount, 'credit' => 0],
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
