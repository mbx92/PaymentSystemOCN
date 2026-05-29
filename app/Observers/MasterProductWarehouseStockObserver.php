<?php

namespace App\Observers;

use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use Illuminate\Support\Facades\DB;

class MasterProductWarehouseStockObserver
{
    public function saved(MasterProductWarehouseStock $stock): void
    {
        $this->syncMasterProductStock($stock->master_product_id);
    }

    public function deleted(MasterProductWarehouseStock $stock): void
    {
        $this->syncMasterProductStock($stock->master_product_id);
    }

    private function syncMasterProductStock(int $masterProductId): void
    {
        $totalQty = (float) MasterProductWarehouseStock::query()
            ->where('master_product_id', $masterProductId)
            ->sum(DB::raw('COALESCE(qty, 0)'));

        MasterProduct::query()
            ->where('id', $masterProductId)
            ->update(['stock' => (int) round($totalQty)]);
    }
}
