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

    /** @var array{categories: list<string>, uoms: list<string>, warehouses: list<string>} */
    public array $autoCreated = ['categories' => [], 'uoms' => [], 'warehouses' => []];

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
            if ($category === '') {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: kategori wajib diisi."];

                continue;
            }
            if (! ProductCategory::query()->where('name', $category)->exists()) {
                ProductCategory::query()->create([
                    'name' => $category,
                    'description' => 'Auto-created from import',
                    'status' => 'active',
                ]);
                $this->autoCreated['categories'][] = $category;
            }

            $uom = isset($data['uom']) ? strtolower(trim((string) $data['uom'])) : '';
            if ($uom === '') {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: UOM wajib diisi."];

                continue;
            }
            if (! Uom::query()->where('code', $uom)->exists()) {
                Uom::query()->create([
                    'code' => $uom,
                    'name' => ucfirst($uom),
                    'status' => 'active',
                ]);
                $this->autoCreated['uoms'][] = $uom;
            }

            $salesChannel = strtolower(trim((string) ($data['sales_channel'] ?? 'both')));
            if (! in_array($salesChannel, ['pos', 'project', 'both'], true)) {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: sales_channel harus pos, project, atau both."];

                continue;
            }

            $productType = strtolower(trim((string) ($data['product_type'] ?? 'finished_goods')));
            if (! in_array($productType, ['finished_goods', 'project_material', 'service'], true)) {
                $this->errors[] = ['row' => $line, 'message' => "SKU {$sku}: product_type harus finished_goods, project_material, atau service."];

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
            $lowStockAlertEnabled = $this->toBool($data['low_stock_alert_enabled'] ?? true);
            $totalSold = max(0, $this->toInt($data['total_sold'] ?? 0));
            if ($productType === MasterProduct::PRODUCT_TYPE_SERVICE) {
                $stock = 0;
                $minStock = 0;
                $lowStockAlertEnabled = false;
                $totalSold = 0;
            }

            $warehouseCode = isset($data['warehouse_code']) ? strtoupper(trim((string) $data['warehouse_code'])) : '';
            $warehouseId = null;
            if ($productType === MasterProduct::PRODUCT_TYPE_SERVICE) {
                $warehouseId = null;
            } elseif ($warehouseCode !== '') {
                $warehouseId = Warehouse::query()->where('code', $warehouseCode)->value('id');
                if (! $warehouseId) {
                    $warehouse = Warehouse::query()->create([
                        'code' => $warehouseCode,
                        'name' => "Gudang {$warehouseCode}",
                        'is_active' => true,
                    ]);
                    $warehouseId = $warehouse->id;
                    $this->autoCreated['warehouses'][] = $warehouseCode;
                }
            } else {
                $warehouseId = Warehouse::query()->where('is_active', true)->orderBy('id')->value('id');
                if (! $warehouseId) {
                    $warehouse = Warehouse::query()->create([
                        'code' => 'WH-MAIN',
                        'name' => 'Gudang Utama',
                        'is_active' => true,
                    ]);
                    $warehouseId = $warehouse->id;
                    $this->autoCreated['warehouses'][] = 'WH-MAIN';
                }
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
                'low_stock_alert_enabled' => $lowStockAlertEnabled,
                'total_sold' => $totalSold,
                'barcode' => $barcode,
            ];

            $product = MasterProduct::query()->updateOrCreate(
                ['sku' => $sku],
                $payload,
            );

            if ($warehouseId) {
                $whRow = MasterProductWarehouseStock::query()->firstOrNew([
                    'master_product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                ]);
                $whRow->qty = $stock;
                if (! $whRow->exists) {
                    $whRow->reserved_qty = 0;
                }
                $whRow->save();
            }

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

    private function toBool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_numeric($v)) {
            return (int) $v === 1;
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'y', 'aktif', 'active'], true);
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
