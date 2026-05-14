<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MasterProductImportTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'sku',
            'name',
            'category',
            'uom',
            'sales_channel',
            'product_type',
            'status',
            'barcode',
            'description',
            'selling_price',
            'stock',
            'min_stock',
            'low_stock_alert_enabled',
            'total_sold',
            'lead_time_days',
            'warehouse_code',
        ];
    }

    public function array(): array
    {
        return [[
            'SKU-DEMO-01',
            'Produk contoh impor',
            'Kemasan Plastik',
            'pcs',
            'both',
            'finished_goods',
            'active',
            '8991001999999',
            'Hapus baris contoh atau ganti dengan data Anda',
            '2500',
            '100',
            '10',
            '1',
            '0',
            '7',
            'TOKO',
        ]];
    }
}
