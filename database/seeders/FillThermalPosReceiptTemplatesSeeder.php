<?php

namespace Database\Seeders;

use App\Models\ErpSetting;
use App\Services\ThermalPosReceiptRenderer;
use Illuminate\Database\Seeder;

/**
 * Mengisi kolom template struk thermal hanya jika masih kosong.
 * Menjalankan ulang seeder ini tidak menimpa template yang sudah disimpan admin.
 */
class FillThermalPosReceiptTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $setting = ErpSetting::query()->firstOrCreate([], [
            'app_name' => 'OCN ERP Suite',
            'app_tagline' => 'Integrated Business Platform',
        ]);

        $renderer = app(ThermalPosReceiptRenderer::class);

        $updates = [];
        if (blank($setting->thermal_pos_header_template)) {
            $updates['thermal_pos_header_template'] = $renderer->defaultHeaderTemplate();
        }
        if (blank($setting->thermal_pos_item_line_template)) {
            $updates['thermal_pos_item_line_template'] = $renderer->defaultItemLineTemplate();
        }
        if (blank($setting->thermal_pos_footer_template)) {
            $updates['thermal_pos_footer_template'] = $renderer->defaultFooterTemplate();
        }

        if ($updates !== []) {
            $setting->update($updates);
        }
    }
}
