<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Kemasan Plastik', 'description' => 'Standing pouch, plastik LDPE/HDPE, ziplock, dan kemasan plastik lainnya'],
            ['name' => 'Kemasan Makanan', 'description' => 'Kotak makan, tray, cup makanan, dan wadah makanan food grade'],
            ['name' => 'Kemasan Kertas', 'description' => 'Paper bag, paper bowl, kraft paper, dan kemasan berbahan kertas'],
            ['name' => 'Kemasan Aluminium Foil', 'description' => 'Aluminium foil pouch, tray foil, dan kemasan foil tahan panas'],
            ['name' => 'Cup & Lid', 'description' => 'Gelas plastik PP/PET, lid sealer, dan tutup cup berbagai ukuran'],
            ['name' => 'Paper Cup', 'description' => 'Gelas kertas hot cup, cold cup, dan cup kopi berbagai ukuran'],
            ['name' => 'Sedotan & Straw', 'description' => 'Sedotan plastik, sedotan kertas, stirrer, dan sedotan bubble tea'],
            ['name' => 'Tali Rafia & Packing', 'description' => 'Tali rafia, stretch film, bubble wrap, dan perlengkapan packing'],
            ['name' => 'Label & Sticker', 'description' => 'Label barcode, sticker produk, thermal label, dan label custom'],
            ['name' => 'Shrink Film', 'description' => 'Shrink wrap PVC/POF, shrink sleeve, dan film penyusut kemasan'],
            ['name' => 'Material CCTV', 'description' => 'Kamera CCTV, DVR, NVR, adaptor, dan perangkat surveillance'],
            ['name' => 'Material Jaringan', 'description' => 'Switch, router, access point, rack, dan perangkat networking'],
            ['name' => 'Kabel & Connector', 'description' => 'Kabel UTP, kabel coaxial, RJ45, BNC connector, dan kabel instalasi'],
            ['name' => 'Aksesoris CCTV', 'description' => 'Bracket, housing, power supply, balun, dan aksesoris CCTV lainnya'],
            ['name' => 'Storage & NVR', 'description' => 'Hard disk surveillance, NVR, cloud storage, dan media penyimpanan'],
            ['name' => 'Alat Tulis Kantor', 'description' => 'Kertas HVS, pena, buku, amplop, dan perlengkapan kantor'],
            ['name' => 'Perlengkapan Toko', 'description' => 'Rak display, etalase, barcode scanner, printer thermal, dan peralatan toko'],
        ];

        foreach ($categories as $category) {
            ProductCategory::query()->firstOrCreate(
                ['name' => $category['name']],
                array_merge($category, ['status' => 'active'])
            );
        }
    }
}
