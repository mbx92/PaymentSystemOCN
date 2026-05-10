<?php

namespace App\Imports;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductCategory;
use App\Models\Uom;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MasterProductsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<array{row: int, message: string}> */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        $line = 1;

        foreach ($rows as $row) {
            $line++;
            $data = $this->normalizeRow($row);
            if ($this->isEmptyRow($data)) {
                continue;
            }

            $sku = isset($data['sku']) ? trim((string) $data['sku']) : '';
            if ($sku === '') {
                $this->errors[] = ['row' => $line, 'message' => 'Kolom sku wajib diisi.'];

                continue;
            }

            $name = isset($data['name']) ? trim((string) $data['name']) : '';
            if ($name === '') {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: name wajib diisi."];

                continue;
            }

            $category = isset($data['category']) ? trim((string) $data['category']) : '';
            if ($category === '' || ! ProductCategory::query()->where('name', $category)->exists()) {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: kategori tidak terdaftar ({$category}). Buat kategori di master data dulu."];

                continue;
            }

            $uom = isset($data['uom']) ? strtolower(trim((string) $data['uom'])) : '';
            if ($uom === '' || ! Uom::query()->where('code', $uom)->exists()) {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: UOM tidak terdaftar ({$uom})."];

                continue;
            }

            $salesChannel = strtolower(trim((string) ($data['sales_channel'] ?? 'both')));
            if (! in_array($salesChannel, ['pos', 'project', 'both'], true)) {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: sales_channel harus pos, project, atau both."];

                continue;
            }

            $productType = strtolower(trim((string) ($data['product_type'] ?? 'finished_goods')));
            if (! in_array($productType, ['finished_goods', 'project_material'], true)) {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: product_type harus finished_goods atau project_material."];

                continue;
            }

            $status = strtolower(trim((string) ($data['status'] ?? 'active')));
            if (! in_array($status, ['active', 'inactive'], true)) {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: status harus active atau inactive."];

                continue;
            }

            $barcode = isset($data['barcode']) ? trim((string) $data['barcode']) : '';
            if ($barcode !== '') {
                $barcodeTaken = MasterProduct::query()
                    ->where('barcode', $barcode)
                    ->where('sku', '!=', $sku)
                    ->exists();
                if ($barcodeTaken) {
                    $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: barcode sudah dipakai produk lain."];

                    continue;
                }
            } else {
                $barcode = null;
            }

            $description = isset($data['description']) ? trim((string) $data['description']) : null;
            if ($description === '') {
                $description = null;
            }

            $sellingPrice = $this->toDecimal($data['selling_price'] ?? 0);
            $stock = max(0, $this->toInt($data['stock'] ?? 0));
            $minStock = max(0, $this->toInt($data['min_stock'] ?? 0));
            $totalSold = max(0, $this->toInt($data['total_sold'] ?? 0));
            $leadTime = $this->toInt($data['lead_time_days'] ?? 7);
            if ($leadTime < 1) {
                $leadTime = 7;
            }
            if ($leadTime > 365) {
                $leadTime = 365;
            }

            $warehouseCode = isset($data['warehouse_code']) ? strtoupper(trim((string) $data['warehouse_code'])) : '';
            $warehouseId = null;
            if ($warehouseCode !== '') {
                $warehouseId = Warehouse::query()->where('code', $warehouseCode)->value('id');
                if (! $warehouseId) {
                    $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: warehouse_code tidak ditemukan ({$warehouseCode})."];

                    continue;
                }
            } else {
                $warehouseId = Warehouse::query()->where('is_active', true)->orderBy('id')->value('id');
            }

            if (! $warehouseId) {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: tidak ada gudang aktif; buat gudang dulu atau isi warehouse_code."];

                continue;
            }

            $payload = [
                'name' => $name,
                'category' => $category,
                'uom' => $uom,
                'sales_channel' => $salesChannel,
                'product_type' => $productType,
                'status' => $status,
                'description' => $description,
                'selling_price' => $sellingPrice,
                'stock' => $stock,
                'min_stock' => $minStock,
                'total_sold' => $totalSold,
                'lead_time_days' => $leadTime,
                'barcode' => $barcode,
            ];

            $product = MasterProduct::query()->updateOrCreate(
                ['sku' => $sku],
                $payload,
            );

            $whRow = MasterProductWarehouseStock::query()->firstOrNew([
                'master_product_id' => $product->id,
                'warehouse_id' => $warehouseId,
            ]);
            $whRow->qty = $stock;
            if (! $whRow->exists) {
                $whRow->reserved_qty = 0;
            }
            $whRow->save();

            $this->imported++;
        }
    }

    /**
     * @param  Collection<int, mixed>|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(Collection|array $row): array
    {
        $arr = is_array($row) ? $row : $row->toArray();
        $out = [];
        foreach ($arr as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            $k = Str::slug((string) $key, '_');
            $out[$k] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        foreach ($data as $v) {
            if ($v !== null && $v !== '') {
                return false;
            }
        }

        return true;
    }

    private function toInt(mixed $v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        if (is_numeric($v)) {
            return (int) $v;
        }

        return (int) preg_replace('/[^\d\-]/', '', (string) $v);
    }

    private function toDecimal(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '0.00';
        }
        if (is_numeric($v)) {
            return number_format((float) $v, 2, '.', '');
        }
        $s = str_replace([','], ['.'], preg_replace('/[^\d.,\-]/', '', (string) $v));

        return number_format((float) $s, 2, '.', '');
    }
}
