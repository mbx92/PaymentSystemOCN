<?php

namespace App\Console\Commands;

use App\ERP\Purchasing\Models\GoodsReceipt;
use App\Services\GoodsReceiptStockCheckService;
use Illuminate\Console\Command;

class CheckGoodsReceiptStockCommand extends Command
{
    protected $signature = 'stock:check-gr
        {gr-number : Nomor goods receipt yang ingin dicek}
        {--json : Tampilkan hasil dalam format JSON}';

    protected $description = 'Inspect stock consistency for a goods receipt, including GR movements, warehouse stock, master stock, and PO received qty';

    public function handle(GoodsReceiptStockCheckService $stockCheck): int
    {
        $grNumber = (string) $this->argument('gr-number');

        $receipt = GoodsReceipt::query()
            ->with([
                'purchaseOrder.lines',
                'warehouse',
                'lines.product.warehouseStocks',
            ])
            ->where('number', $grNumber)
            ->first();

        if (! $receipt) {
            $this->error("Goods receipt {$grNumber} tidak ditemukan.");

            return self::FAILURE;
        }

        $payload = $stockCheck->inspect($receipt);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Goods Receipt Stock Check');
        $this->newLine();
        $this->table(
            ['GR', 'Status', 'Tanggal', 'PO', 'Warehouse'],
            [[
                $payload['receipt']['number'],
                $payload['receipt']['status'],
                $payload['receipt']['received_date'],
                $payload['receipt']['purchase_order'],
                trim(($payload['receipt']['warehouse_id'] ? '#'.$payload['receipt']['warehouse_id'].' ' : '').($payload['receipt']['warehouse_name'] ?? '-')),
            ]]
        );

        $this->table(
            ['SKU', 'Produk', 'GR Qty', 'Expected Net', 'GR In', 'GR Reopen Out', 'GR Net', 'WH Qty', 'WH Reserved', 'All Move Exp', 'Master Stock', 'All WH Qty', 'PO Recv'],
            array_map(fn (array $row): array => [
                $row['sku'],
                $row['name'],
                $row['gr_qty'],
                $row['status_expected_net'],
                $row['gr_in'],
                $row['gr_reopen_out'],
                $row['gr_net'],
                $row['warehouse_qty'],
                $row['warehouse_reserved'],
                $row['all_movement_expected'],
                $row['master_stock'],
                $row['all_warehouse_qty'],
                $row['po_received_qty'],
            ], $payload['lines'])
        );

        if ($payload['warnings'] !== []) {
            $this->warn('Warnings:');
            foreach ($payload['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        } else {
            $this->info('Tidak ada mismatch yang terdeteksi untuk GR ini.');
        }

        $this->newLine();
        $this->line('Tip: gunakan `php artisan stock:check-gr '.$receipt->number.' --json` untuk output yang lebih mudah diparse.');

        return self::SUCCESS;
    }
}
