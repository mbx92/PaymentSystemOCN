<?php

namespace App\Console\Commands;

use App\Services\WarehouseStockRebuildService;
use Illuminate\Console\Command;

class RebuildWarehouseStockFromMovementsCommand extends Command
{
    protected $signature = 'stock:rebuild-from-movements
        {--dry-run : Hanya tampilkan mismatch tanpa menulis database (default jika --apply tidak dipakai)}
        {--apply : Terapkan rebuild stok warehouse + sync reserved + sync master stock}
        {--sample=30 : Jumlah baris mismatch yang ditampilkan}';

    protected $description = 'Rebuild qty warehouse dari product_stock_movements (termasuk purchase_reopen_out / GR reopen & POS reopen), lalu sync reserved project';

    public function handle(WarehouseStockRebuildService $rebuildService): int
    {
        $apply = (bool) $this->option('apply');
        $sampleLimit = max(1, (int) $this->option('sample'));

        $this->info($apply
            ? 'APPLY: rebuild stok dari movement...'
            : 'DRY-RUN: menghitung mismatch stok vs movement...');

        $summary = $apply
            ? $rebuildService->rebuildFromMovements()
            : $rebuildService->summarizeFromMovements();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Rows checked', $summary['warehouse_rows_checked'] ?? 0],
                ['Rows to update / updated', $summary['warehouse_rows_updated'] ?? 0],
                ['Rows created', $summary['warehouse_rows_created'] ?? 0],
                ['Rows zeroed (no movement)', $summary['warehouse_rows_zeroed'] ?? 0],
                ['Total qty before', $summary['total_qty_before'] ?? 0],
                ['Total qty after', $summary['total_qty_after'] ?? 0],
                ['Reservation rows updated', $summary['reservation_rows_updated'] ?? ($apply ? 0 : 'n/a')],
                ['Reservation rows cleared', $summary['reservation_rows_cleared'] ?? ($apply ? 0 : 'n/a')],
            ],
        );

        $samples = collect($summary['mismatches'] ?? [])
            ->take($sampleLimit)
            ->values();

        if ($samples->isNotEmpty()) {
            $productIds = $samples->pluck('master_product_id')->unique()->all();
            $products = \App\Models\MasterProduct::query()
                ->whereIn('id', $productIds)
                ->get(['id', 'sku', 'name'])
                ->keyBy('id');
            $warehouses = \App\ERP\Inventory\Models\Warehouse::query()
                ->whereIn('id', $samples->pluck('warehouse_id')->unique()->all())
                ->get(['id', 'code'])
                ->keyBy('id');

            $this->newLine();
            $this->warn('Sample mismatches (actual → expected from movements):');
            $this->table(
                ['SKU', 'Product', 'WH', 'Actual', 'Expected', 'Delta'],
                $samples->map(function (array $row) use ($products, $warehouses): array {
                    $product = $products->get($row['master_product_id']);
                    $warehouse = $warehouses->get($row['warehouse_id']);

                    return [
                        $product?->sku ?? $row['master_product_id'],
                        \Illuminate\Support\Str::limit((string) ($product?->name ?? '-'), 32),
                        $warehouse?->code ?? $row['warehouse_id'],
                        $row['actual_qty'],
                        $row['expected_qty'],
                        $row['delta_qty'],
                    ];
                })->all(),
            );
        } else {
            $this->info('Tidak ada mismatch stok vs movement.');
        }

        if (! $apply) {
            $this->newLine();
            $this->line('Preview only. Jalankan dengan `--apply` untuk memperbaiki stok di database.');
            $this->line('Movement yang dihitung termasuk: purchase_receipt, purchase_reopen_out, POS/project issue, transfer, opname, manual.');
        } else {
            $this->info('Rebuild selesai. Qty warehouse, reserved project, dan master stock telah diselaraskan ke movement.');
        }

        return self::SUCCESS;
    }
}
