<?php

namespace App\Services;

use App\ERP\Purchasing\Models\GoodsReceipt;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Shared\Enums\DocumentStatus;
use App\ERP\Shared\Services\DocumentNumberService;
use App\Models\MasterProduct;
use App\Models\ProcurementImportStaging;
use App\Models\ProcurementImportStagingLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcurementImportStagingService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
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
                    'mode' => 'approved_pending_post',
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
}
