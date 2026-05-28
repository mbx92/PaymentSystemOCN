<?php

namespace App\Support;

final class ModuleWorkspaceRegistry
{
    /**
     * @var array<string, string>
     */
    private const PINNED_FIRST_MENU_KEYS = [
        'accounting' => 'overview-accounting',
    ];

    /**
     * @return array<string, array{label: string, menus: list<array<string, mixed>>}>
     */
    public static function definitions(): array
    {
        return [
            'accounting' => [
                'label' => 'Accounting',
                'menus' => [
                    ['key' => 'overview-accounting', 'title' => 'Overview Accounting', 'description' => 'Dashboard accounting khusus arus kas, saldo akun kas/bank, dan breakdown COA per usaha.', 'route' => 'erp.accounting.overview', 'icon' => 'chart-bar'],
                    ['key' => 'master-perusahaan', 'title' => 'Master perusahaan', 'description' => 'Kelola entitas usaha (nama legal, NPWP) untuk jurnal dan laporan per perusahaan.', 'route' => 'erp.admin.companies', 'icon' => 'building-office-2'],
                    ['key' => 'chart-of-accounts', 'title' => 'CoA / Chart Of Account', 'description' => 'Daftar akun chart of accounts untuk semua posting akuntansi.', 'route' => 'erp.accounting.coa', 'icon' => 'book-open'],
                    ['key' => 'pengaturan-coa', 'title' => 'Pengaturan COA', 'description' => 'Atur mapping akun untuk posting otomatis POS, invoice project, dan kategori cashflow.', 'route' => 'erp.accounting.coa-settings', 'icon' => 'cog-6-tooth'],
                    ['key' => 'utilitas-accounting', 'title' => 'Utilitas Accounting', 'description' => 'Pindahkan jurnal antar usaha, koreksi COA POS, dan lengkapi akun kas transaksi lama.', 'route' => 'erp.accounting.utilities', 'icon' => 'wrench'],
                    ['key' => 'expenses-tim-project', 'title' => 'Expenses Tim / Project', 'description' => 'Input biaya tim, operasional, referral, dan pengeluaran project. Pemasukan project tetap dari invoice/termin.', 'route' => 'cash-out.index', 'icon' => 'arrow-up-circle'],
                    ['key' => 'cashflow-accounting', 'title' => 'Cashflow Accounting', 'description' => 'Ringkasan arus kas dari invoice, POS, supplier, inventaris, anggota, dan expenses.', 'route' => 'erp.accounting.cashflow', 'icon' => 'arrows-right-left'],
                    ['key' => 'mutasi-kas-bank', 'title' => 'Mutasi Kas/Bank', 'description' => 'Transfer dana antar akun kas/bank (mis. bank ke kas kecil) tanpa mempengaruhi laba rugi.', 'route' => 'erp.accounting.cash-bank-transfer', 'icon' => 'arrows-right-left'],
                    ['key' => 'inventaris', 'title' => 'Inventaris', 'description' => 'Catat pembelian inventaris/aset ke buku besar. Akun aset bisa dipilih, default Peralatan.', 'route' => 'erp.accounting.inventaris', 'icon' => 'archive-box'],
                    ['key' => 'operational', 'title' => 'Operational', 'description' => 'View cepat untuk biaya operasional umum atau per project.', 'route' => 'erp.accounting.operational', 'icon' => 'arrow-up-circle'],
                    ['key' => 'kategori-pengeluaran', 'title' => 'Kategori Pengeluaran', 'description' => 'Mapping kategori expenses ke akun CoA.', 'route' => 'erp.accounting.expense-categories', 'icon' => 'book-open'],
                    ['key' => 'saldo-awal', 'title' => 'Saldo Awal', 'description' => 'Input jurnal pembuka awal periode langsung ke General Ledger.', 'route' => 'erp.accounting.opening-balance', 'icon' => 'scale'],
                    ['key' => 'tutup-buku', 'title' => 'Tutup Buku', 'description' => 'Kunci periode accounting per bulan atau tahun agar transaksi historis tidak berubah.', 'route' => 'erp.accounting.fiscal-periods', 'icon' => 'lock-closed'],
                    ['key' => 'pembayaran', 'title' => 'Pembayaran', 'description' => 'Pusat proses pembayaran project dan tim.', 'route' => 'erp.accounting.payments', 'icon' => 'credit-card'],
                    ['key' => 'rekonsiliasi-kas', 'title' => 'Rekonsiliasi Kas', 'description' => 'Rekap mutasi kas/bank harian dan mingguan per sumber dana.', 'route' => 'erp.accounting.reconciliation', 'icon' => 'calendar-days'],
                    ['key' => 'general-ledger', 'title' => 'General Ledger', 'description' => 'Lihat jurnal umum yang sudah diposting.', 'route' => 'reports.general-ledger', 'icon' => 'book-open'],
                    ['key' => 'neraca-saldo', 'title' => 'Neraca Saldo', 'description' => 'Ringkasan saldo debit-kredit per akun.', 'route' => 'reports.trial-balance', 'icon' => 'scale'],
                ],
            ],
            'sales' => [
                'label' => 'Sales',
                'menus' => [
                    ['key' => 'pos-produk', 'title' => 'POS Produk', 'description' => 'Kasir untuk penjualan produk kemasan plastik dan makanan.', 'route' => 'erp.sales.pos', 'icon' => 'shopping-cart', 'newTab' => true, 'url' => route('erp.sales.pos', ['fullscreen' => 1])],
                    ['key' => 'transaksi', 'title' => 'Transaksi', 'description' => 'Riwayat transaksi POS yang sudah diproses.', 'route' => 'erp.sales.pos.transactions', 'icon' => 'document-text'],
                    ['key' => 'invoice-project', 'title' => 'Invoice Project', 'description' => 'Pembuatan invoice untuk project software, CCTV, dan jaringan.', 'route' => 'erp.sales.project-invoices', 'icon' => 'document-text'],
                ],
            ],
            'purchasing' => [
                'label' => 'Purchasing',
                'menus' => [
                    ['key' => 'manajemen-supplier', 'title' => 'Manajemen Supplier', 'description' => 'Kelola data supplier dan performa lead time.', 'route' => 'erp.purchasing.suppliers', 'icon' => 'truck'],
                    ['key' => 'purchase-order', 'title' => 'Purchase Order', 'description' => 'Buat dan monitor PO pembelian barang.', 'route' => 'erp.purchasing.purchase-orders', 'icon' => 'clipboard-list'],
                    ['key' => 'penerimaan-barang', 'title' => 'Penerimaan Barang', 'description' => 'Catat penerimaan barang dari supplier (GRN).', 'route' => 'erp.purchasing.goods-receipts', 'icon' => 'inbox-arrow-down'],
                    ['key' => 'perencanaan-reorder', 'title' => 'Perencanaan Reorder', 'description' => 'Saran reorder dari minimum stock, kekurangan project (material & finished goods), dan PO outstanding.', 'route' => 'erp.purchasing.reorder-planning', 'icon' => 'sparkles'],
                ],
            ],
            'inventory' => [
                'label' => 'Inventory',
                'menus' => [
                    ['key' => 'master-produk', 'title' => 'Master Produk', 'description' => 'Kelola produk kemasan, channel POS, dan material project.', 'route' => 'erp.master-products.index', 'icon' => 'cube'],
                    ['key' => 'manajemen-kategori', 'title' => 'Manajemen Kategori', 'description' => 'Kelola kategori produk untuk inventory.', 'route' => 'erp.inventory.categories', 'icon' => 'tag'],
                    ['key' => 'warehouse', 'title' => 'Warehouse', 'description' => 'Kelola gudang aktif untuk operasional stok, POS, dan purchasing.', 'route' => 'erp.inventory.warehouses', 'icon' => 'archive-box'],
                    ['key' => 'uom-konversi', 'title' => 'UoM & Konversi', 'description' => 'Kelola satuan unit dan konversi antar satuan.', 'route' => 'erp.inventory.uoms', 'icon' => 'arrows-right-left'],
                    ['key' => 'manajemen-stok', 'title' => 'Manajemen Stok', 'description' => 'Atur stok minimum, total terjual, dan kontrol stok.', 'route' => 'erp.inventory.stock-management', 'icon' => 'archive-box'],
                    ['key' => 'mutasi-stok', 'title' => 'Mutasi Stok', 'description' => 'Transfer stok produk antar gudang/warehouse.', 'route' => 'erp.inventory.stock-transfer', 'icon' => 'arrows-right-left'],
                    ['key' => 'stock-movement', 'title' => 'Stock Movement', 'description' => 'Lihat histori pergerakan stok per produk dan warehouse.', 'route' => 'erp.inventory.stock-movements', 'icon' => 'arrows-up-down'],
                    ['key' => 'stok-opname', 'title' => 'Stok Opname', 'description' => 'Penyesuaian stok fisik berkala untuk akurasi inventory.', 'route' => 'erp.inventory.stock-opname', 'icon' => 'clipboard-check'],
                    ['key' => 'report-stok', 'title' => 'Report Stok', 'description' => 'Grafik stok, alert stok rendah, dan top selling produk.', 'route' => 'erp.inventory.stock-report', 'icon' => 'presentation-chart-line'],
                ],
            ],
            'projects' => [
                'label' => 'Projects',
                'menus' => [
                    ['key' => 'overview-project', 'title' => 'Overview Project', 'description' => 'Dashboard statistik seluruh project: status, nilai kontrak, pembayaran, task, dan material.', 'route' => 'projects.overview', 'icon' => 'chart-bar'],
                    ['key' => 'tipe-project', 'title' => 'Tipe Project', 'description' => 'Master tipe project, label, default, dan capability seperti board task atau budget item.', 'route' => 'erp.projects.project-types.index', 'icon' => 'tag'],
                    ['key' => 'budgeting-project', 'title' => 'Budgeting Project', 'description' => 'Siapkan budget calon project sebelum deal, lalu convert ke project.', 'route' => 'erp.projects.budgets.index', 'icon' => 'clipboard-list'],
                    ['key' => 'daftar-project', 'title' => 'Daftar Project', 'description' => 'Kelola proyek, status, dan termin pembayaran.', 'route' => 'projects.index', 'icon' => 'git-branch'],
                    ['key' => 'role-tim-project', 'title' => 'Role Tim Project', 'description' => 'Master role global untuk assign tim project.', 'route' => 'erp.projects.team-roles.index', 'icon' => 'identification'],
                    ['key' => 'pembagian-tim', 'title' => 'Pembagian Tim', 'description' => 'Atur komposisi dan distribusi pembagian tim.', 'route' => 'team-distribution.calculator', 'icon' => 'users'],
                    ['key' => 'pembayaran-anggota', 'title' => 'Pembayaran Anggota', 'description' => 'Bayar distribusi tim dan pantau status pelunasan.', 'route' => 'erp.accounting.payments.member', 'icon' => 'user-circle'],
                ],
            ],
            'hr' => [
                'label' => 'HR',
                'menus' => [
                    ['key' => 'karyawan', 'title' => 'Karyawan', 'description' => 'Data karyawan: nomor pegawai, kontak, jabatan, dan gaji pokok.', 'route' => 'erp.hr.employees', 'icon' => 'identification'],
                    ['key' => 'legal', 'title' => 'Legal', 'description' => 'File manager dokumen legal: folder, upload, dan pratinjau PDF.', 'route' => 'erp.hr.legal', 'icon' => 'document-text'],
                ],
            ],
            'crm' => [
                'label' => 'CRM',
                'menus' => [
                    ['key' => 'lead-management', 'title' => 'Lead Management', 'description' => 'Pusat data calon customer, sumber lead, status prospek, dan PIC follow-up.', 'route' => 'erp.crm.leads', 'icon' => 'user-circle'],
                    ['key' => 'customer-database', 'title' => 'Customer Database', 'description' => 'Master customer lintas sub usaha agar histori komunikasi dan transaksi tetap terhubung.', 'route' => 'erp.crm.customers', 'icon' => 'identification'],
                    ['key' => 'pipeline-penjualan', 'title' => 'Pipeline Penjualan', 'description' => 'Tahapan penawaran, deal value, dan peluang closing yang bisa dipantau tim.', 'route' => 'erp.crm.pipelines', 'icon' => 'clipboard-list'],
                    ['key' => 'aktivitas-follow-up', 'title' => 'Aktivitas Follow-up', 'description' => 'Log call, chat, meeting, reminder, dan next action untuk setiap prospek/customer.', 'route' => 'erp.crm.activities', 'icon' => 'share'],
                ],
            ],
            'reporting' => [
                'label' => 'Reporting',
                'menus' => [
                    ['key' => 'cashflow-report', 'title' => 'Cashflow Report', 'description' => 'Laporan arus kas dengan filter periode dan pivot kategori/project.', 'route' => 'reports.cashflow', 'icon' => 'arrows-right-left'],
                    ['key' => 'revenue-per-usaha', 'title' => 'Revenue per Usaha', 'description' => 'Pendapatan tiap usaha berdasarkan jurnal revenue yang sudah diposting.', 'route' => 'reports.company-revenue', 'icon' => 'presentation-chart-line'],
                    ['key' => 'laba-rugi-per-usaha', 'title' => 'Laba Rugi per Usaha', 'description' => 'Ringkasan revenue, beban, dan net profit per entitas usaha.', 'route' => 'reports.company-profit-loss', 'icon' => 'chart-bar'],
                    ['key' => 'project-report', 'title' => 'Project Report', 'description' => 'Laporan project dengan nilai kontrak, cash in/out, dan pivot status/tipe.', 'route' => 'reports.projects', 'icon' => 'presentation-chart-line'],
                    ['key' => 'pos-report', 'title' => 'POS Report', 'description' => 'Laporan transaksi POS dengan pivot status, channel, dan metode bayar.', 'route' => 'reports.pos', 'icon' => 'shopping-cart'],
                    ['key' => 'general-ledger', 'title' => 'General Ledger', 'description' => 'Laporan buku besar terintegrasi.', 'route' => 'reports.general-ledger', 'icon' => 'book-open'],
                    ['key' => 'neraca-saldo', 'title' => 'Neraca Saldo', 'description' => 'Laporan neraca saldo akun.', 'route' => 'reports.trial-balance', 'icon' => 'scale'],
                    ['key' => 'rekap-bulanan', 'title' => 'Rekap Bulanan', 'description' => 'Laporan periodik bulanan.', 'route' => 'reports.monthly', 'icon' => 'calendar-days'],
                    ['key' => 'rekap-stok-bulanan', 'title' => 'Rekap Stok Bulanan', 'description' => 'Ringkasan data stok per bulan untuk inventory.', 'route' => 'erp.inventory.stock-report', 'icon' => 'archive-box'],
                ],
            ],
            'administration' => [
                'label' => 'Administration',
                'menus' => [
                    ['key' => 'master-perusahaan', 'title' => 'Master perusahaan', 'description' => 'Tambah dan ubah entitas usaha untuk multi-buku besar, saldo awal, dan pemilih perusahaan aktif.', 'route' => 'erp.admin.companies', 'icon' => 'building-office-2'],
                    ['key' => 'maintenance-mode', 'title' => 'Maintenance mode', 'description' => 'Matikan seluruh area ERP atau per modul. Role admin tetap bisa mengakses semua halaman.', 'route' => 'erp.admin.maintenance-mode', 'icon' => 'clipboard-check'],
                    ['key' => 'erp-setting', 'title' => 'ERP Setting', 'description' => 'Atur identitas aplikasi ERP seperti logo, nama aplikasi, dan tagline.', 'route' => 'erp.admin.erp-settings', 'icon' => 'document-text'],
                    ['key' => 'setting-nomor-dokumen', 'title' => 'Setting Nomor Dokumen', 'description' => 'Atur prefix dan sequence nomor dokumen agar konsisten lintas modul.', 'route' => 'erp.admin.document-sequences', 'icon' => 'document-text'],
                    ['key' => 'metode-pembayaran', 'title' => 'Metode Pembayaran', 'description' => 'Kelola daftar metode pembayaran global untuk POS dan invoice.', 'route' => 'erp.admin.payment-methods', 'icon' => 'credit-card'],
                    ['key' => 'parser-rules-chatbot', 'title' => 'Parser Rules Chatbot', 'description' => 'Atur rule parser berbasis keyword untuk chatbot ERP tanpa LLM.', 'route' => 'erp.admin.parser-rules', 'icon' => 'sparkles'],
                    ['key' => 'monitoring-log', 'title' => 'Monitoring Log', 'description' => 'Pantau aktivitas ERP, transaksi, dan error aplikasi secara terpusat.', 'route' => 'erp.admin.system-logs.index', 'icon' => 'circle-stack'],
                    ['key' => 'monitoring-server', 'title' => 'Monitoring server', 'description' => 'Latensi kueri DB, ukuran basis data, TCP ke host DB, dan latensi HTTP keluar dari server.', 'route' => 'erp.admin.server-monitoring', 'icon' => 'chart-bar'],
                    ['key' => 'printer-label', 'title' => 'Printer & label', 'description' => 'Thermal LAN (struk POS), label Windows (SMB), label LAN (TSPL), dan profil label (ZPL/EPL) dalam satu halaman.', 'route' => 'erp.admin.printer-and-label', 'icon' => 'printer'],
                    ['key' => 'impor-seeder-data', 'title' => 'Impor & Seeder Data', 'description' => 'Impor data dari Excel dan jalankan seeder database untuk COA, kategori, UoM, label, dan chatbot.', 'route' => 'erp.admin.data-import', 'icon' => 'arrow-up-tray'],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function moduleKeys(): array
    {
        return array_keys(self::definitions());
    }

    public static function labelFor(string $moduleKey): ?string
    {
        return self::definitions()[$moduleKey]['label'] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function menusFor(string $moduleKey): array
    {
        return self::definitions()[$moduleKey]['menus'] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function defaultMenuOrderFor(string $moduleKey): array
    {
        return self::pinMenuFirst($moduleKey, array_values(array_map(
            static fn (array $menu): string => (string) $menu['key'],
            self::menusFor($moduleKey),
        )));
    }

    /**
     * @param  list<string>  $order
     * @return list<string>
     */
    public static function normalizeMenuOrder(string $moduleKey, array $order): array
    {
        $defaults = self::defaultMenuOrderFor($moduleKey);
        if ($defaults === []) {
            return [];
        }

        $validLookup = array_fill_keys($defaults, true);
        $normalized = [];

        foreach ($order as $key) {
            if (! is_string($key) || ! isset($validLookup[$key]) || in_array($key, $normalized, true)) {
                continue;
            }

            $normalized[] = $key;
        }

        foreach ($defaults as $defaultKey) {
            if (! in_array($defaultKey, $normalized, true)) {
                $normalized[] = $defaultKey;
            }
        }

        return self::pinMenuFirst($moduleKey, $normalized);
    }

    /**
     * @param  list<string>  $order
     * @return list<string>
     */
    private static function pinMenuFirst(string $moduleKey, array $order): array
    {
        $pinnedKey = self::PINNED_FIRST_MENU_KEYS[$moduleKey] ?? null;

        if (! is_string($pinnedKey) || ! in_array($pinnedKey, $order, true)) {
            return $order;
        }

        return array_values([
            $pinnedKey,
            ...array_values(array_filter($order, static fn (string $key): bool => $key !== $pinnedKey)),
        ]);
    }
}
