<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetItem;
use Illuminate\Support\Str;

class BudgetCatalogPromotionService
{
    /**
     * Saat budget disetujui customer (status deal), item dari katalog supplier
     * dipromosikan menjadi master product jika belum ada.
     *
     * @return int jumlah item yang dipromosikan
     */
    public function promoteCatalogItems(ProjectBudget $budget): int
    {
        $budget->loadMissing('items');
        $promoted = 0;

        foreach ($budget->items as $item) {
            if ($item->master_product_id || ! $item->catalog_ref) {
                continue;
            }

            $product = $this->findOrCreateMasterProduct($item, $budget);
            $item->update(['master_product_id' => $product->id]);
            $promoted++;
        }

        return $promoted;
    }

    /**
     * Pastikan setiap baris budget terhubung ke master product sebelum convert ke project.
     *
     * @return int jumlah item yang baru dihubungkan
     */
    public function ensureAllItemsHaveMasterProduct(ProjectBudget $budget): int
    {
        $budget->loadMissing('items');
        $linked = 0;

        foreach ($budget->items as $item) {
            if ($item->master_product_id || trim((string) $item->name) === '' || (float) $item->qty <= 0) {
                continue;
            }

            $product = $item->catalog_ref
                ? $this->findOrCreateMasterProduct($item, $budget)
                : $this->findOrCreateAdHocMasterProduct($item, $budget);

            $item->update(['master_product_id' => $product->id]);
            $linked++;
        }

        return $linked;
    }

    private function findOrCreateMasterProduct(ProjectBudgetItem $item, ProjectBudget $budget): MasterProduct
    {
        $sku = Str::upper(trim((string) $item->catalog_ref));
        $category = $item->catalog_category
            ? trim((string) $item->catalog_sheet).' - '.trim((string) $item->catalog_category)
            : (string) ($item->catalog_sheet ?? 'Katalog Supplier');

        return MasterProduct::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'name' => $item->name,
                'category' => $category,
                'uom' => $item->uom ?: 'unit',
                'sales_channel' => 'project',
                'product_type' => $this->resolveProductType($item),
                'status' => 'active',
                'description' => 'Dipromosikan dari katalog supplier saat budget disetujui: '.$budget->name,
                'unit_cost' => (float) $item->unit_cost,
                'selling_price' => (float) $item->unit_price,
                'stock' => 0,
                'min_stock' => 0,
                'low_stock_alert_enabled' => false,
                'total_sold' => 0,
            ],
        );
    }

    private function findOrCreateAdHocMasterProduct(ProjectBudgetItem $item, ProjectBudget $budget): MasterProduct
    {
        $sku = 'BDG-'.$budget->id.'-'.$item->id;
        $isService = ($item->item_type ?? '') === 'service';

        return MasterProduct::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'name' => $item->name,
                'category' => $item->catalog_category
                    ?: ($isService ? 'Jasa Project' : 'Material Project'),
                'uom' => $item->uom ?: ($isService ? 'paket' : 'unit'),
                'sales_channel' => 'project',
                'product_type' => $this->resolveProductType($item),
                'status' => 'active',
                'description' => 'Dibuat otomatis dari budget project: '.$budget->name,
                'unit_cost' => (float) $item->unit_cost,
                'selling_price' => (float) $item->unit_price,
                'stock' => 0,
                'min_stock' => 0,
                'low_stock_alert_enabled' => false,
                'total_sold' => 0,
            ],
        );
    }

    private function resolveProductType(ProjectBudgetItem $item): string
    {
        return ($item->item_type ?? '') === 'service'
            ? MasterProduct::PRODUCT_TYPE_SERVICE
            : MasterProduct::PRODUCT_TYPE_PROJECT_MATERIAL;
    }
}
