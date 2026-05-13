<?php

namespace Database\Seeders;

use App\ERP\Accounting\Models\Account;
use App\ERP\Core\Models\Company;
use App\ERP\Core\Models\Currency;
use App\ERP\Core\Models\DocumentSequence;
use App\ERP\Core\Models\FiscalPeriod;
use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Purchasing\Models\GoodsReceipt;
use App\ERP\Purchasing\Models\GoodsReceiptLine;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Purchasing\Models\PurchaseOrderLine;
use App\ERP\Purchasing\Models\Vendor;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\Uom;
use App\Models\UomConversion;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);

        Company::query()->firstOrCreate(
            ['name' => 'OCN Tech'],
            [
                'legal_name' => 'PT OCN Teknologi',
                'tax_id' => '00.000.000.0-000.000',
                'is_active' => true,
            ]
        );

        Currency::query()->firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'is_base' => true]
        );

        FiscalPeriod::query()->firstOrCreate(
            ['name' => now()->format('Y')],
            ['start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear(), 'is_closed' => false]
        );

        $chartOfAccounts = [
            ['code' => '1001', 'name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1101', 'name' => 'Piutang Usaha', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1201', 'name' => 'Persediaan Barang Dagang', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '2001', 'name' => 'Hutang Usaha', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2005', 'name' => 'Pendapatan Diterima Dimuka', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2006', 'name' => 'Dana Titipan Material Client', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '4001', 'name' => 'Pendapatan Jasa', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4003', 'name' => 'Pendapatan Project', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5009', 'name' => 'HPP - Harga Pokok Penjualan', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];

        foreach ($chartOfAccounts as $account) {
            Account::query()->firstOrCreate(['code' => $account['code']], $account);
        }

        $masterProducts = [
            ['sku' => 'PKG-SP-12X20', 'barcode' => '899100120001', 'name' => 'Standing Pouch 12x20', 'category' => 'Kemasan Plastik', 'uom' => 'pcs', 'sales_channel' => 'pos', 'product_type' => 'finished_goods', 'status' => 'active', 'selling_price' => 1850, 'stock' => 2400, 'min_stock' => 500, 'total_sold' => 7800, 'lead_time_days' => 5],
            ['sku' => 'PKG-PL-30X50', 'barcode' => '899100130005', 'name' => 'Plastik LDPE 30x50', 'category' => 'Kemasan Plastik', 'uom' => 'pack', 'sales_channel' => 'both', 'product_type' => 'finished_goods', 'status' => 'active', 'selling_price' => 15000, 'stock' => 320, 'min_stock' => 100, 'total_sold' => 2350, 'lead_time_days' => 7],
            ['sku' => 'PKG-LID-95', 'barcode' => '899100140011', 'name' => 'Lid Cup 95mm', 'category' => 'Kemasan Makanan', 'uom' => 'dus', 'sales_channel' => 'pos', 'product_type' => 'finished_goods', 'status' => 'active', 'selling_price' => 42000, 'stock' => 85, 'min_stock' => 120, 'total_sold' => 1420, 'lead_time_days' => 10],
            ['sku' => 'CCTV-UTP-CAT6', 'barcode' => '899200110001', 'name' => 'Kabel UTP Cat6', 'category' => 'Material CCTV', 'uom' => 'roll', 'sales_channel' => 'project', 'product_type' => 'project_material', 'status' => 'active', 'selling_price' => 775000, 'stock' => 42, 'min_stock' => 20, 'total_sold' => 180, 'lead_time_days' => 14],
        ];

        foreach ($masterProducts as $product) {
            MasterProduct::query()->firstOrCreate(['sku' => $product['sku']], $product);
        }

        $warehouseToko = Warehouse::query()->firstOrCreate(
            ['code' => 'TOKO'],
            ['name' => 'Warehouse Toko', 'address' => 'Toko', 'is_active' => true]
        );
        $warehouseCctv = Warehouse::query()->firstOrCreate(
            ['code' => 'CCTV'],
            ['name' => 'Warehouse CCTV', 'address' => 'CCTV', 'is_active' => true]
        );

        $allProducts = MasterProduct::query()->get();
        foreach ($allProducts as $p) {
            $targetWarehouseId = $p->product_type === 'project_material' ? $warehouseCctv->id : $warehouseToko->id;
            MasterProductWarehouseStock::query()->updateOrCreate(
                ['master_product_id' => $p->id, 'warehouse_id' => $targetWarehouseId],
                ['qty' => $p->stock]
            );
            $otherWarehouseId = $targetWarehouseId === $warehouseToko->id ? $warehouseCctv->id : $warehouseToko->id;
            MasterProductWarehouseStock::query()->firstOrCreate(
                ['master_product_id' => $p->id, 'warehouse_id' => $otherWarehouseId],
                ['qty' => 0]
            );
        }

        $movementTemplates = [
            ['type' => 'in', 'qty' => 120],
            ['type' => 'out', 'qty' => 90],
        ];
        $productsForMovement = MasterProduct::query()->whereIn('sku', ['PKG-SP-12X20', 'PKG-PL-30X50', 'PKG-LID-95'])->get();
        foreach ($productsForMovement as $product) {
            for ($month = 1; $month <= 12; $month++) {
                foreach ($movementTemplates as $template) {
                    $qty = $template['qty'] + random_int(0, 40);
                    ProductStockMovement::query()->firstOrCreate([
                        'master_product_id' => $product->id,
                        'movement_date' => now()->startOfYear()->addMonths($month - 1)->addDays(5)->toDateString(),
                        'movement_type' => $template['type'],
                        'qty' => $qty,
                    ], [
                        'note' => 'Seeder monthly movement',
                    ]);
                }
            }
        }

        $categories = [
            ['name' => 'Kemasan Plastik', 'description' => 'Produk kemasan plastik', 'status' => 'active'],
            ['name' => 'Kemasan Makanan', 'description' => 'Produk kemasan makanan', 'status' => 'active'],
            ['name' => 'Material CCTV', 'description' => 'Material project CCTV', 'status' => 'active'],
        ];
        foreach ($categories as $category) {
            ProductCategory::query()->firstOrCreate(['name' => $category['name']], $category);
        }

        $uoms = [
            ['code' => 'pcs', 'name' => 'Pieces', 'status' => 'active'],
            ['code' => 'pack', 'name' => 'Pack', 'status' => 'active'],
            ['code' => 'dus', 'name' => 'Dus', 'status' => 'active'],
            ['code' => 'roll', 'name' => 'Roll', 'status' => 'active'],
        ];
        foreach ($uoms as $uom) {
            Uom::query()->firstOrCreate(['code' => $uom['code']], $uom);
        }

        $paymentMethods = [
            ['code' => 'cash', 'name' => 'Tunai', 'description' => 'Pembayaran tunai langsung', 'status' => 'active'],
            ['code' => 'transfer', 'name' => 'Transfer Bank', 'description' => 'Transfer ke rekening perusahaan', 'status' => 'active'],
            ['code' => 'qris', 'name' => 'QRIS', 'description' => 'Pembayaran via QRIS', 'status' => 'active'],
            ['code' => 'debit', 'name' => 'Kartu Debit', 'description' => 'Pembayaran via EDC/debit', 'status' => 'active'],
        ];
        foreach ($paymentMethods as $method) {
            PaymentMethod::query()->updateOrCreate(['code' => $method['code']], $method);
        }

        $documentSequences = [
            ['module' => 'sales', 'document_type' => 'project_invoice', 'prefix' => 'INV-PRJ', 'running_number' => 0, 'padding_length' => 6],
            ['module' => 'purchasing', 'document_type' => 'purchase_order', 'prefix' => 'PO', 'running_number' => 0, 'padding_length' => 6],
            ['module' => 'purchasing', 'document_type' => 'goods_receipt', 'prefix' => 'GRN', 'running_number' => 0, 'padding_length' => 6],
            ['module' => 'purchasing', 'document_type' => 'supplier_code', 'prefix' => 'SUP', 'running_number' => 0, 'padding_length' => 3],
            ['module' => 'accounting', 'document_type' => 'journal_entry', 'prefix' => 'JE', 'running_number' => 0, 'padding_length' => 6],
            ['module' => 'accounting', 'document_type' => 'payable_bill', 'prefix' => 'BILL', 'running_number' => 0, 'padding_length' => 6],
        ];
        foreach ($documentSequences as $seq) {
            DocumentSequence::query()->updateOrCreate(
                ['module' => $seq['module'], 'document_type' => $seq['document_type']],
                $seq
            );
        }

        $this->call(ErpChatParserRuleSeeder::class);

        $pcs = Uom::query()->where('code', 'pcs')->first();
        $pack = Uom::query()->where('code', 'pack')->first();
        $dus = Uom::query()->where('code', 'dus')->first();
        if ($pack && $pcs) {
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $pack->id, 'to_uom_id' => $pcs->id],
                ['multiplier' => 100]
            );
        }
        if ($dus && $pack) {
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $dus->id, 'to_uom_id' => $pack->id],
                ['multiplier' => 10]
            );
        }

        $vendors = [
            [
                'code' => 'SUP-001',
                'name' => 'PT Plastik Nusantara',
                'email' => 'sales@plastik-nusantara.test',
                'phone' => '0812-0000-0001',
                'address' => 'Jl. Industri Raya No. 88, Bekasi',
                'tax_id' => '01.234.567.8-901.000',
                'payment_terms' => 'Net 14',
                'lead_time_days' => 7,
                'notes' => 'Supplier utama kemasan plastik food grade.',
                'is_active' => true,
            ],
            [
                'code' => 'SUP-002',
                'name' => 'CV Kemasan Prima',
                'email' => 'order@kemasan-prima.test',
                'phone' => '0812-0000-0002',
                'address' => 'Jl. Raya Bogor KM 28, Cibinong',
                'tax_id' => '02.345.678.9-012.000',
                'payment_terms' => 'Net 7',
                'lead_time_days' => 5,
                'notes' => 'Lead time cepat untuk order kecil.',
                'is_active' => true,
            ],
        ];

        foreach ($vendors as $vendorData) {
            Vendor::query()->updateOrCreate(
                ['code' => $vendorData['code']],
                $vendorData
            );
        }

        $plastikVendor = Vendor::query()->where('code', 'SUP-001')->first();
        $primaVendor = Vendor::query()->where('code', 'SUP-002')->first();
        $productA = MasterProduct::query()->where('sku', 'PKG-SP-12X20')->first();
        $productB = MasterProduct::query()->where('sku', 'PKG-PL-30X50')->first();
        $productC = MasterProduct::query()->where('sku', 'PKG-LID-95')->first();

        if ($plastikVendor && $primaVendor && $productA && $productB && $productC) {
            $po1 = PurchaseOrder::query()->updateOrCreate(
                ['number' => 'PO-2026-001'],
                [
                    'vendor_id' => $plastikVendor->id,
                    'order_date' => now()->startOfMonth()->toDateString(),
                    'eta_date' => now()->startOfMonth()->addDays(12)->toDateString(),
                    'total_amount' => 8250000,
                    'status' => 'approved',
                ]
            );

            PurchaseOrderLine::query()->updateOrCreate(
                ['purchase_order_id' => $po1->id, 'master_product_id' => $productA->id],
                ['qty' => 200, 'unit_price' => 12000, 'line_total' => 2400000]
            );
            PurchaseOrderLine::query()->updateOrCreate(
                ['purchase_order_id' => $po1->id, 'master_product_id' => $productB->id],
                ['qty' => 50, 'unit_price' => 117000, 'line_total' => 5850000]
            );

            $po2 = PurchaseOrder::query()->updateOrCreate(
                ['number' => 'PO-2026-002'],
                [
                    'vendor_id' => $primaVendor->id,
                    'order_date' => now()->startOfMonth()->addDays(3)->toDateString(),
                    'eta_date' => now()->startOfMonth()->addDays(15)->toDateString(),
                    'total_amount' => 4600000,
                    'status' => 'draft',
                ]
            );

            PurchaseOrderLine::query()->updateOrCreate(
                ['purchase_order_id' => $po2->id, 'master_product_id' => $productC->id],
                ['qty' => 110, 'unit_price' => 41818.18, 'line_total' => 4600000]
            );

            $grn1 = GoodsReceipt::query()->updateOrCreate(
                ['number' => 'GRN-2026-001'],
                [
                    'purchase_order_id' => $po1->id,
                    'received_date' => now()->startOfMonth()->addDays(10)->toDateString(),
                    'warehouse_name' => 'Gudang Utama',
                    'status' => 'posted',
                ]
            );
            GoodsReceiptLine::query()->updateOrCreate(
                ['goods_receipt_id' => $grn1->id, 'master_product_id' => $productA->id],
                ['qty_received' => 200]
            );
            GoodsReceiptLine::query()->updateOrCreate(
                ['goods_receipt_id' => $grn1->id, 'master_product_id' => $productB->id],
                ['qty_received' => 50]
            );

            $grn2 = GoodsReceipt::query()->updateOrCreate(
                ['number' => 'GRN-2026-002'],
                [
                    'purchase_order_id' => $po2->id,
                    'received_date' => now()->startOfMonth()->addDays(11)->toDateString(),
                    'warehouse_name' => 'Gudang Utama',
                    'status' => 'approved',
                ]
            );
            GoodsReceiptLine::query()->updateOrCreate(
                ['goods_receipt_id' => $grn2->id, 'master_product_id' => $productC->id],
                ['qty_received' => 80]
            );
        }

        $this->call(FillThermalPosReceiptTemplatesSeeder::class);

        $this->call(CoaSeeder::class);
        $this->call(ProductCategorySeeder::class);
        $this->call(UomSeeder::class);
        $this->call(WarehouseSeeder::class);
        $this->call(LabelProfileSeeder::class);
        $this->call(ProjectFlowSeeder::class);
    }
}
