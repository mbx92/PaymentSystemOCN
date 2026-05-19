<?php

use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->after('uom')->constrained('warehouses')->nullOnDelete();
        });

        MasterProduct::query()
            ->whereNull('warehouse_id')
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    $warehouseId = MasterProductWarehouseStock::query()
                        ->where('master_product_id', $product->id)
                        ->orderByDesc('qty')
                        ->orderBy('warehouse_id')
                        ->value('warehouse_id');

                    if ($warehouseId) {
                        $product->forceFill(['warehouse_id' => $warehouseId])->save();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
