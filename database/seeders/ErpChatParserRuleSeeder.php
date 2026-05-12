<?php

namespace Database\Seeders;

use App\Models\ErpChatParserRule;
use Illuminate\Database\Seeder;

class ErpChatParserRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // ── Greeting ──
            [
                'name' => 'Greeting / sapaan',
                'intent_key' => 'greeting',
                'keywords' => ['halo', 'hai', 'hi', 'hello', 'hey', 'selamat'],
                'match_mode' => 'or',
                'priority' => 1,
                'is_active' => true,
                'notes' => 'Sapaan dan salam.',
            ],
            [
                'name' => 'Greeting / terima kasih',
                'intent_key' => 'greeting',
                'keywords' => ['terima kasih', 'makasih', 'thanks', 'thx', 'tq'],
                'match_mode' => 'or',
                'priority' => 2,
                'is_active' => true,
                'notes' => 'Ucapan terima kasih.',
            ],

            // ── Produk & Stok ──
            [
                'name' => 'Lookup stok produk',
                'intent_key' => 'stock_lookup',
                'keywords' => ['stok'],
                'priority' => 10,
                'is_active' => true,
                'notes' => 'Intent untuk cek sisa stok produk.',
            ],
            [
                'name' => 'Lookup stok barang (EN)',
                'intent_key' => 'stock_lookup',
                'keywords' => ['stock'],
                'priority' => 11,
                'is_active' => true,
                'notes' => 'Sinonim bahasa Inggris untuk stok.',
            ],
            [
                'name' => 'Cek sisa barang',
                'intent_key' => 'stock_lookup',
                'keywords' => ['sisa barang', 'barang tersedia', 'ada barang', 'persediaan'],
                'match_mode' => 'or',
                'priority' => 12,
                'is_active' => true,
                'notes' => 'Variasi pertanyaan stok.',
            ],
            [
                'name' => 'Harga produk',
                'intent_key' => 'product_price_lookup',
                'keywords' => ['harga'],
                'priority' => 20,
                'is_active' => true,
                'notes' => 'Intent untuk cek harga produk.',
            ],
            [
                'name' => 'Harga produk (EN)',
                'intent_key' => 'product_price_lookup',
                'keywords' => ['price'],
                'match_mode' => 'or',
                'priority' => 21,
                'is_active' => true,
                'notes' => 'Intent harga bahasa Inggris.',
            ],
            [
                'name' => 'Berapa harga produk',
                'intent_key' => 'product_price_lookup',
                'keywords' => ['berapa harga', 'brp harga', 'harga berapa', 'biaya produk'],
                'match_mode' => 'or',
                'priority' => 22,
                'is_active' => true,
                'notes' => 'Variasi pertanyaan harga.',
            ],
            [
                'name' => 'Detail produk',
                'intent_key' => 'product_detail',
                'keywords' => ['detail produk', 'info produk', 'data produk', 'detail barang'],
                'match_mode' => 'or',
                'priority' => 23,
                'is_active' => true,
                'notes' => 'Menampilkan info lengkap produk (stok + harga + SKU + barcode).',
            ],
            [
                'name' => 'Detail produk (informal)',
                'intent_key' => 'product_detail',
                'keywords' => ['lihat produk', 'cek produk', 'cari produk', 'info barang'],
                'match_mode' => 'or',
                'priority' => 24,
                'is_active' => true,
                'notes' => 'Variasi informal detail produk.',
            ],
            [
                'name' => 'Stok rendah / low stock',
                'intent_key' => 'low_stock_alert',
                'keywords' => ['stok rendah', 'stock rendah', 'low stock', 'stok menipis', 'stok habis', 'hampir habis'],
                'match_mode' => 'or',
                'priority' => 25,
                'is_active' => true,
                'notes' => 'Daftar produk yang stoknya di bawah minimum.',
            ],
            [
                'name' => 'Produk terlaris',
                'intent_key' => 'top_selling_products',
                'keywords' => ['terlaris', 'produk terlaris', 'best seller', 'paling laku', 'top produk', 'paling banyak terjual'],
                'match_mode' => 'or',
                'priority' => 26,
                'is_active' => true,
                'notes' => 'Top 10 produk terlaris bulan ini berdasarkan POS.',
            ],

            // ── Invoice ──
            [
                'name' => 'Invoice belum dibayar',
                'intent_key' => 'invoice_unpaid_list',
                'keywords' => ['invoice', 'belum dibayar'],
                'priority' => 30,
                'is_active' => true,
                'notes' => 'Daftar invoice project dengan status belum dibayar.',
            ],
            [
                'name' => 'Invoice belum lunas',
                'intent_key' => 'invoice_unpaid_list',
                'keywords' => ['invoice belum lunas', 'tagihan belum dibayar', 'piutang belum masuk'],
                'match_mode' => 'or',
                'priority' => 31,
                'is_active' => true,
                'notes' => 'Variasi invoice belum dibayar.',
            ],
            [
                'name' => 'Invoice jatuh tempo',
                'intent_key' => 'invoice_due_list',
                'keywords' => ['invoice', 'jatuh tempo'],
                'priority' => 32,
                'is_active' => true,
                'notes' => 'Daftar invoice yang mendekati/melewati jatuh tempo.',
            ],
            [
                'name' => 'Invoice overdue',
                'intent_key' => 'invoice_due_list',
                'keywords' => ['overdue', 'terlambat bayar', 'tagihan terlambat', 'lewat tempo'],
                'match_mode' => 'or',
                'priority' => 33,
                'is_active' => true,
                'notes' => 'Invoice yang sudah lewat jatuh tempo.',
            ],

            // ── POS ──
            [
                'name' => 'Penjualan POS hari ini',
                'intent_key' => 'pos_sales_today',
                'keywords' => ['pos', 'hari ini'],
                'priority' => 40,
                'is_active' => true,
                'notes' => 'Ringkasan transaksi POS harian.',
            ],
            [
                'name' => 'Penjualan hari ini (umum)',
                'intent_key' => 'pos_sales_today',
                'keywords' => ['penjualan hari ini', 'sales hari ini', 'omset hari ini', 'omzet hari ini'],
                'match_mode' => 'or',
                'priority' => 40,
                'is_active' => true,
                'notes' => 'Variasi penjualan hari ini.',
            ],
            [
                'name' => 'Penjualan POS kemarin',
                'intent_key' => 'pos_sales_yesterday',
                'keywords' => ['pos', 'kemarin'],
                'priority' => 41,
                'is_active' => true,
                'notes' => 'Ringkasan transaksi POS kemarin.',
            ],
            [
                'name' => 'Penjualan kemarin (umum)',
                'intent_key' => 'pos_sales_yesterday',
                'keywords' => ['penjualan kemarin', 'sales kemarin', 'omset kemarin'],
                'match_mode' => 'or',
                'priority' => 41,
                'is_active' => true,
                'notes' => 'Variasi penjualan kemarin.',
            ],
            [
                'name' => 'Penjualan POS bulan ini',
                'intent_key' => 'pos_sales_month',
                'keywords' => ['pos bulan ini', 'penjualan bulan ini', 'sales bulan ini', 'omset bulan ini'],
                'match_mode' => 'or',
                'priority' => 42,
                'is_active' => true,
                'notes' => 'Ringkasan transaksi POS bulan ini.',
            ],
            [
                'name' => 'Penjualan POS bulan lalu',
                'intent_key' => 'pos_sales_last_month',
                'keywords' => ['pos bulan lalu', 'penjualan bulan lalu', 'sales bulan lalu', 'omset bulan lalu'],
                'match_mode' => 'or',
                'priority' => 43,
                'is_active' => true,
                'notes' => 'Ringkasan transaksi POS bulan lalu.',
            ],

            // ── Cashflow ──
            [
                'name' => 'Cashflow hari ini',
                'intent_key' => 'cashflow_today',
                'keywords' => ['cashflow', 'hari ini'],
                'priority' => 50,
                'is_active' => true,
                'notes' => 'Ringkasan kas masuk/keluar hari ini.',
            ],
            [
                'name' => 'Kas hari ini',
                'intent_key' => 'cashflow_today',
                'keywords' => ['kas hari ini', 'arus kas hari ini', 'cash hari ini'],
                'match_mode' => 'or',
                'priority' => 50,
                'is_active' => true,
                'notes' => 'Variasi cashflow hari ini.',
            ],
            [
                'name' => 'Cashflow kemarin',
                'intent_key' => 'cashflow_yesterday',
                'keywords' => ['cashflow', 'kemarin'],
                'priority' => 51,
                'is_active' => true,
                'notes' => 'Ringkasan kas masuk/keluar kemarin.',
            ],
            [
                'name' => 'Kas kemarin',
                'intent_key' => 'cashflow_yesterday',
                'keywords' => ['kas kemarin', 'arus kas kemarin'],
                'match_mode' => 'or',
                'priority' => 51,
                'is_active' => true,
                'notes' => 'Variasi cashflow kemarin.',
            ],
            [
                'name' => 'Cashflow bulan ini',
                'intent_key' => 'cashflow_month',
                'keywords' => ['cashflow bulan ini', 'kas bulan ini', 'arus kas bulan ini'],
                'match_mode' => 'or',
                'priority' => 52,
                'is_active' => true,
                'notes' => 'Ringkasan kas masuk/keluar bulan berjalan.',
            ],
            [
                'name' => 'Cashflow bulan lalu',
                'intent_key' => 'cashflow_last_month',
                'keywords' => ['cashflow bulan lalu', 'kas bulan lalu', 'arus kas bulan lalu'],
                'match_mode' => 'or',
                'priority' => 53,
                'is_active' => true,
                'notes' => 'Ringkasan kas masuk/keluar bulan sebelumnya.',
            ],

            // ── Project & Operational ──
            [
                'name' => 'Daftar project aktif',
                'intent_key' => 'project_active_list',
                'keywords' => ['project aktif', 'proyek aktif', 'project berjalan', 'daftar project'],
                'match_mode' => 'or',
                'priority' => 54,
                'is_active' => true,
                'notes' => 'Daftar project yang sedang berjalan.',
            ],
            [
                'name' => 'Daftar project (informal)',
                'intent_key' => 'project_active_list',
                'keywords' => ['project apa saja', 'ada project apa', 'project yang jalan'],
                'match_mode' => 'or',
                'priority' => 54,
                'is_active' => true,
                'notes' => 'Variasi informal daftar project.',
            ],
            [
                'name' => 'Biaya operasional',
                'intent_key' => 'operational_summary',
                'keywords' => ['biaya operasional', 'operasional', 'pengeluaran', 'biaya bulan ini'],
                'match_mode' => 'or',
                'priority' => 55,
                'is_active' => true,
                'notes' => 'Perbandingan biaya operasional bulan ini vs bulan lalu.',
            ],
            [
                'name' => 'Pengeluaran perusahaan',
                'intent_key' => 'operational_summary',
                'keywords' => ['total pengeluaran', 'biaya perusahaan', 'expense', 'berapa pengeluaran'],
                'match_mode' => 'or',
                'priority' => 56,
                'is_active' => true,
                'notes' => 'Variasi biaya operasional.',
            ],

            // ── Invoice send ──
            [
                'name' => 'Kirim invoice ke email',
                'intent_key' => 'send_invoice',
                'keywords' => ['kirim invoice'],
                'match_mode' => 'or',
                'priority' => 60,
                'is_active' => true,
                'notes' => 'Mengirim invoice project via email dengan konfirmasi.',
            ],
            [
                'name' => 'Email invoice',
                'intent_key' => 'send_invoice',
                'keywords' => ['email invoice', 'send invoice', 'kirim tagihan'],
                'match_mode' => 'or',
                'priority' => 61,
                'is_active' => true,
                'notes' => 'Variasi kirim invoice.',
            ],
            [
                'name' => 'List invoice terkirim',
                'intent_key' => 'invoice_sent_list',
                'keywords' => ['list invoice', 'invoice dikirim', 'invoice terkirim', 'riwayat invoice', 'history invoice'],
                'match_mode' => 'or',
                'priority' => 62,
                'is_active' => true,
                'notes' => 'Menampilkan riwayat invoice yang dikirim lewat chatbot.',
            ],

            // ── Help (lowest priority) ──
            [
                'name' => 'Bantuan / Help',
                'intent_key' => 'help',
                'keywords' => ['bantuan', 'help', 'cara pakai', 'tutorial', 'fitur apa saja', 'bisa apa', 'menu', 'panduan'],
                'match_mode' => 'or',
                'priority' => 999,
                'is_active' => true,
                'notes' => 'Menampilkan daftar fitur dan contoh pertanyaan chatbot.',
            ],
        ];

        foreach ($rules as $rule) {
            ErpChatParserRule::query()->updateOrCreate(
                ['intent_key' => $rule['intent_key'], 'name' => $rule['name']],
                $rule
            );
        }
    }
}
