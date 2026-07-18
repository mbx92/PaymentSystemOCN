<?php

namespace Database\Seeders;

use App\ERP\Inventory\Models\Shelf;
use App\ERP\Inventory\Models\ShelfSlot;
use App\Models\MasterProduct;
use Illuminate\Database\Seeder;

class ShelfSeeder extends Seeder
{
    public function run(): void
    {
        $products = MasterProduct::query()->inRandomOrder()->take(20)->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No MasterProduct found. Please run MasterProductSeeder first.');

            return;
        }

        $shelfConfigs = [
            ['code' => 'A1', 'name' => 'Minuman Ringan', 'row' => 0, 'col' => 0],
            ['code' => 'A2', 'name' => 'Snack & Makanan Ringan', 'row' => 0, 'col' => 1],
            ['code' => 'A3', 'name' => 'Bumbu Dapur', 'row' => 0, 'col' => 2],
            ['code' => 'B1', 'name' => 'Produk Susu', 'row' => 1, 'col' => 0],
            ['code' => 'B2', 'name' => 'Mie & Pasta', 'row' => 1, 'col' => 1],
            ['code' => 'B3', 'name' => 'Sembako', 'row' => 1, 'col' => 2],
            ['code' => 'C1', 'name' => 'Perlengkapan Mandi', 'row' => 2, 'col' => 0],
            ['code' => 'C2', 'name' => 'Produk Kebersihan', 'row' => 2, 'col' => 1],
            ['code' => 'C3', 'name' => 'Obat & Kesehatan', 'row' => 2, 'col' => 2],
            ['code' => 'D1', 'name' => 'Elektronik Kecil', 'row' => 3, 'col' => 0],
            ['code' => 'D2', 'name' => 'Alat Tulis', 'row' => 3, 'col' => 1],
            ['code' => 'D3', 'name' => 'Mainan Anak', 'row' => 3, 'col' => 2],
        ];

        $productIndex = 0;

        foreach ($shelfConfigs as $cfg) {
            $shelf = Shelf::query()->updateOrCreate(
                ['code' => $cfg['code']],
                [
                    'name' => $cfg['name'],
                    'row_position' => $cfg['row'],
                    'col_position' => $cfg['col'],
                ]
            );

            for ($tier = 4; $tier >= 1; $tier--) {
                $slotsPerTier = rand(3, 5);

                for ($pos = 0; $pos < $slotsPerTier; $pos++) {
                    $product = $products[$productIndex % count($products)] ?? null;
                    $productIndex++;

                    $qty = rand(0, 30);
                    $minQty = rand(3, 12);

                    if ($cfg['code'] === 'B1' && $pos === 0) {
                        $qty = 0; // Empty stock to show red
                    }
                    if ($cfg['code'] === 'A2' && $pos === 1) {
                        $qty = 2;
                        $minQty = 10; // Low stock to show yellow
                    }

                    ShelfSlot::query()->updateOrCreate(
                        [
                            'shelf_id' => $shelf->id,
                            'tier' => $tier,
                            'slot_position' => $pos,
                        ],
                        [
                            'product_id' => $product?->id,
                            'qty' => $qty,
                            'min_qty' => $minQty,
                        ]
                    );
                }
            }
        }

        $this->command?->info('Shelf seeder selesai: '.count($shelfConfigs).' rak dibuat.');
    }
}
