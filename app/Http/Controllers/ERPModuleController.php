<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ERPModuleController extends Controller
{
    public function accounting(): Response
    {
        return $this->renderModule('Accounting', [
            ['title' => 'CoA / Chart Of Account', 'description' => 'Daftar akun chart of accounts untuk semua posting akuntansi.', 'route' => 'erp.accounting.coa', 'icon' => 'book-open'],
            ['title' => 'Cashflow', 'description' => 'Submenu kas masuk dan kas keluar dalam satu tempat.', 'route' => 'erp.accounting.cashflow', 'icon' => 'arrows-right-left'],
            ['title' => 'Operational', 'description' => 'Pencatatan biaya operasional umum (non-project) atau per project.', 'route' => 'erp.accounting.operational', 'icon' => 'arrow-up-circle'],
            ['title' => 'Kategori Pengeluaran', 'description' => 'Mapping kategori kas keluar ke akun CoA untuk jurnal yang valid.', 'route' => 'erp.accounting.expense-categories', 'icon' => 'book-open'],
            ['title' => 'Pembayaran', 'description' => 'Pusat proses pembayaran project dan tim.', 'route' => 'erp.accounting.payments', 'icon' => 'credit-card'],
            ['title' => 'Rekonsiliasi Kas', 'description' => 'Rekap mutasi kas/bank harian dan mingguan per sumber dana.', 'route' => 'erp.accounting.reconciliation', 'icon' => 'calendar-days'],
            ['title' => 'General Ledger', 'description' => 'Lihat jurnal umum yang sudah diposting.', 'route' => 'reports.general-ledger', 'icon' => 'book-open'],
            ['title' => 'Neraca Saldo', 'description' => 'Ringkasan saldo debit-kredit per akun.', 'route' => 'reports.trial-balance', 'icon' => 'scale'],
        ]);
    }

    public function payments(): Response
    {
        return Inertia::render('ERP/Accounting/Payments');
    }

    public function sales(): Response
    {
        return $this->renderModule('Sales', [
            ['title' => 'POS Produk', 'description' => 'Kasir untuk penjualan produk kemasan plastik dan makanan.', 'route' => 'erp.sales.pos', 'icon' => 'shopping-cart', 'newTab' => true, 'url' => route('erp.sales.pos', ['fullscreen' => 1])],
            ['title' => 'Transaksi', 'description' => 'Riwayat transaksi POS yang sudah diproses.', 'route' => 'erp.sales.pos.transactions', 'icon' => 'document-text'],
            ['title' => 'Invoice Project', 'description' => 'Pembuatan invoice untuk project software, CCTV, dan jaringan.', 'route' => 'erp.sales.project-invoices', 'icon' => 'document-text'],
        ]);
    }

    public function purchasing(): Response
    {
        return $this->renderModule('Purchasing', [
            ['title' => 'Manajemen Supplier', 'description' => 'Kelola data supplier dan performa lead time.', 'route' => 'erp.purchasing.suppliers', 'icon' => 'truck'],
            ['title' => 'Purchase Order', 'description' => 'Buat dan monitor PO pembelian barang.', 'route' => 'erp.purchasing.purchase-orders', 'icon' => 'clipboard-list'],
            ['title' => 'Penerimaan Barang', 'description' => 'Catat penerimaan barang dari supplier (GRN).', 'route' => 'erp.purchasing.goods-receipts', 'icon' => 'inbox-arrow-down'],
            ['title' => 'Perencanaan Reorder', 'description' => 'Saran reorder otomatis berdasarkan min stock dan lead time.', 'route' => 'erp.purchasing.reorder-planning', 'icon' => 'sparkles'],
        ]);
    }

    public function inventory(): Response
    {
        return $this->renderModule('Inventory', [
            ['title' => 'Master Produk', 'description' => 'Kelola produk kemasan, channel POS, dan material project.', 'route' => 'erp.master-products.index', 'icon' => 'cube'],
            ['title' => 'Manajemen Kategori', 'description' => 'Kelola kategori produk untuk inventory.', 'route' => 'erp.inventory.categories', 'icon' => 'tag'],
            ['title' => 'Warehouse', 'description' => 'Kelola gudang aktif untuk operasional stok, POS, dan purchasing.', 'route' => 'erp.inventory.warehouses', 'icon' => 'archive-box'],
            ['title' => 'UoM & Konversi', 'description' => 'Kelola satuan unit dan konversi antar satuan.', 'route' => 'erp.inventory.uoms', 'icon' => 'arrows-right-left'],
            ['title' => 'Manajemen Stok', 'description' => 'Atur stok minimum, total terjual, dan kontrol stok.', 'route' => 'erp.inventory.stock-management', 'icon' => 'archive-box'],
            ['title' => 'Stock Movement', 'description' => 'Lihat histori pergerakan stok per produk dan warehouse.', 'route' => 'erp.inventory.stock-movements', 'icon' => 'arrows-up-down'],
            ['title' => 'Stok Opname', 'description' => 'Penyesuaian stok fisik berkala untuk akurasi inventory.', 'route' => 'erp.inventory.stock-opname', 'icon' => 'clipboard-check'],
            ['title' => 'Report Stok', 'description' => 'Grafik stok, alert stok rendah, dan top selling produk.', 'route' => 'erp.inventory.stock-report', 'icon' => 'presentation-chart-line'],
        ]);
    }

    public function projects(): Response
    {
        return $this->renderModule('Projects', [
            ['title' => 'Budgeting Project', 'description' => 'Siapkan budget calon project sebelum deal, lalu convert ke project.', 'route' => 'erp.projects.budgets.index', 'icon' => 'clipboard-list'],
            ['title' => 'Daftar Project', 'description' => 'Kelola proyek, status, dan termin pembayaran.', 'route' => 'projects.index', 'icon' => 'git-branch'],
            ['title' => 'Role Tim Project', 'description' => 'Master role global untuk assign tim project.', 'route' => 'erp.projects.team-roles.index', 'icon' => 'identification'],
            ['title' => 'Pembagian Tim', 'description' => 'Atur komposisi dan distribusi pembagian tim.', 'route' => 'team-distribution.calculator', 'icon' => 'users'],
            ['title' => 'Pembayaran Anggota', 'description' => 'Lihat pembayaran anggota per project.', 'route' => 'reports.member-payments', 'icon' => 'user-circle'],
        ]);
    }

    public function hr(): Response
    {
        return $this->renderModule('HR', [
            ['title' => 'Karyawan', 'description' => 'Data karyawan: nomor pegawai, kontak, jabatan, dan gaji pokok.', 'route' => 'erp.hr.employees', 'icon' => 'identification'],
            ['title' => 'Legal', 'description' => 'File manager dokumen legal: folder, upload, dan pratinjau PDF.', 'route' => 'erp.hr.legal', 'icon' => 'document-text'],
        ]);
    }

    public function reporting(): Response
    {
        return $this->renderModule('Reporting', [
            ['title' => 'General Ledger', 'description' => 'Laporan buku besar terintegrasi.', 'route' => 'reports.general-ledger', 'icon' => 'book-open'],
            ['title' => 'Neraca Saldo', 'description' => 'Laporan neraca saldo akun.', 'route' => 'reports.trial-balance', 'icon' => 'scale'],
            ['title' => 'Profit Project', 'description' => 'Laporan laba per project.', 'route' => 'reports.project-profit', 'icon' => 'chart-bar'],
            ['title' => 'Rekap Bulanan', 'description' => 'Laporan periodik bulanan.', 'route' => 'reports.monthly', 'icon' => 'calendar-days'],
            ['title' => 'Rekap Stok Bulanan', 'description' => 'Ringkasan data stok per bulan untuk inventory.', 'route' => 'erp.inventory.stock-report', 'icon' => 'archive-box'],
        ]);
    }

    public function administration(): Response
    {
        return $this->renderModule('Administration', [
            ['title' => 'Maintenance mode', 'description' => 'Matikan seluruh area ERP atau per modul. Role admin tetap bisa mengakses semua halaman.', 'route' => 'erp.admin.maintenance-mode', 'icon' => 'clipboard-check'],
            ['title' => 'ERP Setting', 'description' => 'Atur identitas aplikasi ERP seperti logo, nama aplikasi, dan tagline.', 'route' => 'erp.admin.erp-settings', 'icon' => 'document-text'],
            ['title' => 'Setting Nomor Dokumen', 'description' => 'Atur prefix dan sequence nomor dokumen agar konsisten lintas modul.', 'route' => 'erp.admin.document-sequences', 'icon' => 'document-text'],
            ['title' => 'Metode Pembayaran', 'description' => 'Kelola daftar metode pembayaran global untuk POS dan invoice.', 'route' => 'erp.admin.payment-methods', 'icon' => 'credit-card'],
            ['title' => 'Parser Rules Chatbot', 'description' => 'Atur rule parser berbasis keyword untuk chatbot ERP tanpa LLM.', 'route' => 'erp.admin.parser-rules', 'icon' => 'sparkles'],
            ['title' => 'Monitoring Log', 'description' => 'Pantau aktivitas ERP, transaksi, dan error aplikasi secara terpusat.', 'route' => 'erp.admin.system-logs.index', 'icon' => 'circle-stack'],
            ['title' => 'Monitoring server', 'description' => 'Latensi kueri DB, ukuran basis data, TCP ke host DB, dan latensi HTTP keluar dari server.', 'route' => 'erp.admin.server-monitoring', 'icon' => 'chart-bar'],
            ['title' => 'Printer & label', 'description' => 'Thermal LAN (struk POS), label Windows (SMB), label LAN (TSPL), dan profil label (ZPL/EPL) dalam satu halaman.', 'route' => 'erp.admin.printer-and-label', 'icon' => 'printer'],
            ['title' => 'Impor data (Excel)', 'description' => 'Unduh template dan impor master produk atau project dari .xlsx / .xls / .csv.', 'route' => 'erp.admin.data-import', 'icon' => 'arrow-up-tray'],
        ]);
    }

    private function renderModule(string $module, array $menus): Response
    {
        return Inertia::render('ERP/Modules/Index', [
            'module' => $module,
            'menus' => $menus,
        ]);
    }
}
