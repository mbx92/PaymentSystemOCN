<?php

namespace Database\Seeders;

use App\Models\LabelProfile;
use Illuminate\Database\Seeder;

class LabelProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            [
                'name' => '50x30mm ZPL - Standard Barcode',
                'width_mm' => 50,
                'height_mm' => 30,
                'dpi' => 203,
                'margin_left_mm' => 2,
                'margin_top_mm' => 2,
                'gap_mm' => 2,
                'rows' => 1,
                'protocol' => 'zpl',
            ],
            [
                'name' => '50x25mm ZPL - Compact Barcode',
                'width_mm' => 50,
                'height_mm' => 25,
                'dpi' => 203,
                'margin_left_mm' => 2,
                'margin_top_mm' => 2,
                'gap_mm' => 2,
                'rows' => 1,
                'protocol' => 'zpl',
            ],
            [
                'name' => '40x30mm ZPL - Mini Label',
                'width_mm' => 40,
                'height_mm' => 30,
                'dpi' => 203,
                'margin_left_mm' => 2,
                'margin_top_mm' => 2,
                'gap_mm' => 2,
                'rows' => 1,
                'protocol' => 'zpl',
            ],
            [
                'name' => '100x50mm ZPL - Shipping Label',
                'width_mm' => 100,
                'height_mm' => 50,
                'dpi' => 203,
                'margin_left_mm' => 3,
                'margin_top_mm' => 3,
                'gap_mm' => 3,
                'rows' => 1,
                'protocol' => 'zpl',
            ],
            [
                'name' => '100x150mm ZPL - Large Shipping',
                'width_mm' => 100,
                'height_mm' => 150,
                'dpi' => 203,
                'margin_left_mm' => 3,
                'margin_top_mm' => 3,
                'gap_mm' => 3,
                'rows' => 1,
                'protocol' => 'zpl',
            ],
            [
                'name' => '76x127mm ZPL - Price Tag (3x5 inch)',
                'width_mm' => 76,
                'height_mm' => 127,
                'dpi' => 203,
                'margin_left_mm' => 3,
                'margin_top_mm' => 3,
                'gap_mm' => 3,
                'rows' => 1,
                'protocol' => 'zpl',
            ],
            [
                'name' => '33x15mm ZPL - Jewelry/Small Item (3-row)',
                'width_mm' => 33,
                'height_mm' => 15,
                'dpi' => 203,
                'margin_left_mm' => 1,
                'margin_top_mm' => 1,
                'gap_mm' => 2,
                'rows' => 3,
                'protocol' => 'zpl',
            ],
            [
                'name' => '50x30mm TSPL - Standard TSPL',
                'width_mm' => 50,
                'height_mm' => 30,
                'dpi' => 203,
                'margin_left_mm' => 2,
                'margin_top_mm' => 2,
                'gap_mm' => 2,
                'rows' => 1,
                'protocol' => 'tspl',
            ],
            [
                'name' => '100x50mm TSPL - Shipping TSPL',
                'width_mm' => 100,
                'height_mm' => 50,
                'dpi' => 203,
                'margin_left_mm' => 3,
                'margin_top_mm' => 3,
                'gap_mm' => 3,
                'rows' => 1,
                'protocol' => 'tspl',
            ],
        ];

        foreach ($profiles as $profile) {
            LabelProfile::query()->firstOrCreate(
                ['name' => $profile['name']],
                $profile
            );
        }
    }
}
