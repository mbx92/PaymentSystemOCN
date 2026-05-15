<?php

namespace Database\Seeders;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProductCategory;
use App\Models\Uom;
use Illuminate\Database\Seeder;

class MasterProductSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-MAIN'],
            ['name' => 'Gudang Utama', 'address' => 'Gudang utama penyimpanan produk', 'is_active' => true],
        );

        $products = [
            ['sku' => 'CAM-IP-DOME-2MP', 'barcode' => '899300100001', 'name' => 'IP Camera Dome 2MP PoE', 'category' => 'IP Camera', 'uom' => 'unit', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 650000, 'stock' => 18, 'min_stock' => 5, 'lead_time_days' => 7],
            ['sku' => 'CAM-IP-BULLET-4MP', 'barcode' => '899300100002', 'name' => 'IP Camera Bullet 4MP Outdoor', 'category' => 'IP Camera', 'uom' => 'unit', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 875000, 'stock' => 12, 'min_stock' => 4, 'lead_time_days' => 7],
            ['sku' => 'CAM-AHD-DOME-2MP', 'barcode' => '899300100003', 'name' => 'Kamera CCTV AHD Dome 2MP', 'category' => 'Kamera CCTV', 'uom' => 'unit', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 325000, 'stock' => 25, 'min_stock' => 8, 'lead_time_days' => 5],
            ['sku' => 'CAM-AHD-BULLET-2MP', 'barcode' => '899300100004', 'name' => 'Kamera CCTV AHD Bullet 2MP', 'category' => 'Kamera CCTV', 'uom' => 'unit', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 350000, 'stock' => 22, 'min_stock' => 8, 'lead_time_days' => 5],
            ['sku' => 'NVR-8CH-POE', 'barcode' => '899300100005', 'name' => 'NVR 8 Channel PoE', 'category' => 'NVR', 'uom' => 'unit', 'sales_channel' => 'project', 'product_type' => 'project_material', 'selling_price' => 1850000, 'stock' => 6, 'min_stock' => 2, 'lead_time_days' => 10],
            ['sku' => 'DVR-8CH-5MP', 'barcode' => '899300100006', 'name' => 'DVR 8 Channel 5MP', 'category' => 'DVR', 'uom' => 'unit', 'sales_channel' => 'project', 'product_type' => 'project_material', 'selling_price' => 1450000, 'stock' => 7, 'min_stock' => 2, 'lead_time_days' => 10],
            ['sku' => 'HDD-SURV-1TB', 'barcode' => '899300100007', 'name' => 'HDD Surveillance 1TB', 'category' => 'Storage', 'uom' => 'unit', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 780000, 'stock' => 10, 'min_stock' => 3, 'lead_time_days' => 7],
            ['sku' => 'HDD-SURV-2TB', 'barcode' => '899300100008', 'name' => 'HDD Surveillance 2TB', 'category' => 'Storage', 'uom' => 'unit', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 1125000, 'stock' => 8, 'min_stock' => 3, 'lead_time_days' => 7],
            ['sku' => 'SW-POE-8P-GIGA', 'barcode' => '899300100009', 'name' => 'Switch PoE 8 Port Gigabit', 'category' => 'Switch PoE', 'uom' => 'unit', 'sales_channel' => 'project', 'product_type' => 'project_material', 'selling_price' => 1350000, 'stock' => 9, 'min_stock' => 3, 'lead_time_days' => 10],
            ['sku' => 'SW-POE-16P-GIGA', 'barcode' => '899300100010', 'name' => 'Switch PoE 16 Port Gigabit', 'category' => 'Switch PoE', 'uom' => 'unit', 'sales_channel' => 'project', 'product_type' => 'project_material', 'selling_price' => 2450000, 'stock' => 4, 'min_stock' => 2, 'lead_time_days' => 14],
            ['sku' => 'CBL-UTP-CAT6-305', 'barcode' => '899300100011', 'name' => 'Kabel UTP Cat6 305m', 'category' => 'Kabel', 'uom' => 'roll', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 875000, 'stock' => 14, 'min_stock' => 5, 'lead_time_days' => 7],
            ['sku' => 'CBL-RG59-305', 'barcode' => '899300100012', 'name' => 'Kabel Coaxial RG59 305m', 'category' => 'Kabel', 'uom' => 'roll', 'sales_channel' => 'project', 'product_type' => 'project_material', 'selling_price' => 725000, 'stock' => 8, 'min_stock' => 3, 'lead_time_days' => 7],
            ['sku' => 'CON-RJ45-CAT6', 'barcode' => '899300100013', 'name' => 'Connector RJ45 Cat6', 'category' => 'Connector', 'uom' => 'pack', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 45000, 'stock' => 35, 'min_stock' => 10, 'lead_time_days' => 3],
            ['sku' => 'CON-BNC-COMP', 'barcode' => '899300100014', 'name' => 'Connector BNC Compression', 'category' => 'Connector', 'uom' => 'pack', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 65000, 'stock' => 24, 'min_stock' => 8, 'lead_time_days' => 3],
            ['sku' => 'PSU-CCTV-12V10A', 'barcode' => '899300100015', 'name' => 'Power Supply CCTV 12V 10A', 'category' => 'Power Supply CCTV', 'uom' => 'unit', 'sales_channel' => 'both', 'product_type' => 'project_material', 'selling_price' => 185000, 'stock' => 16, 'min_stock' => 5, 'lead_time_days' => 5],
            ['sku' => 'BOX-PANEL-30X40', 'barcode' => '899300100016', 'name' => 'Box Panel 30x40 Outdoor', 'category' => 'Box Panel', 'uom' => 'unit', 'sales_channel' => 'project', 'product_type' => 'project_material', 'selling_price' => 225000, 'stock' => 11, 'min_stock' => 4, 'lead_time_days' => 5],
            ['sku' => 'RACK-WALL-6U', 'barcode' => '899300100017', 'name' => 'Rack Wallmount 6U', 'category' => 'Rack', 'uom' => 'unit', 'sales_channel' => 'project', 'product_type' => 'project_material', 'selling_price' => 950000, 'stock' => 5, 'min_stock' => 2, 'lead_time_days' => 10],
            ['sku' => 'AP-INDOOR-AC1200', 'barcode' => '899300100018', 'name' => 'Access Point Indoor AC1200', 'category' => 'Access Point', 'uom' => 'unit', 'sales_channel' => 'both', 'product_type' => 'finished_goods', 'selling_price' => 725000, 'stock' => 15, 'min_stock' => 5, 'lead_time_days' => 7],
            ['sku' => 'RTR-MIKROTIK-HAP', 'barcode' => '899300100019', 'name' => 'Router Mikrotik hAP Series', 'category' => 'Router', 'uom' => 'unit', 'sales_channel' => 'both', 'product_type' => 'finished_goods', 'selling_price' => 685000, 'stock' => 10, 'min_stock' => 4, 'lead_time_days' => 7],
            ['sku' => 'SVC-INSTALL-CCTV', 'barcode' => '899300100020', 'name' => 'Jasa Instalasi CCTV per Titik', 'category' => 'Jasa Instalasi', 'uom' => 'titik', 'sales_channel' => 'project', 'product_type' => 'service', 'selling_price' => 250000, 'stock' => 0, 'min_stock' => 0, 'lead_time_days' => 1],
        ];

        foreach ($products as $product) {
            ProductCategory::query()->firstOrCreate(
                ['name' => $product['category']],
                ['description' => 'Auto-created from product seeder', 'status' => 'active'],
            );

            Uom::query()->firstOrCreate(
                ['code' => $product['uom']],
                ['name' => ucfirst($product['uom']), 'status' => 'active'],
            );

            $masterProduct = MasterProduct::query()->firstOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'status' => 'active',
                    'description' => 'Produk demo dari MasterProductSeeder',
                    'low_stock_alert_enabled' => $product['product_type'] !== MasterProduct::PRODUCT_TYPE_SERVICE,
                    'total_sold' => 0,
                ]),
            );

            if ($masterProduct->product_type === MasterProduct::PRODUCT_TYPE_SERVICE) {
                continue;
            }

            MasterProductWarehouseStock::query()->firstOrCreate(
                ['master_product_id' => $masterProduct->id, 'warehouse_id' => $warehouse->id],
                ['qty' => $masterProduct->stock, 'reserved_qty' => 0],
            );
        }
    }
}
