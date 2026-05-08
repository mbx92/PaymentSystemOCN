<?php

namespace App\Http\Controllers;

use App\Models\LegalContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HRLegalController extends Controller
{
    public function index(): Response
    {
        $contracts = LegalContract::query()
            ->with('creator:id,name')
            ->latest()
            ->get()
            ->map(fn (LegalContract $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'contract_number' => $c->contract_number,
                'contract_date' => $c->contract_date?->format('Y-m-d'),
                'contract_type' => $c->contract_type,
                'status' => $c->status,
                'creator_name' => $c->creator?->name ?? '-',
                'created_at' => $c->created_at?->format('Y-m-d H:i'),
            ]);

        $templates = collect(File::files(base_path('docs')))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['doc', 'docx'], true))
            ->sortBy(fn ($file) => $file->getFilename())
            ->values()
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size_kb' => round($file->getSize() / 1024, 1),
                'download_url' => route('erp.hr.legal.templates.download', ['file' => $file->getFilename()]),
            ]);

        return Inertia::render('ERP/HR/Legal', [
            'contracts' => $contracts,
            'templates' => $templates,
            'contractTypes' => self::contractTypeOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ERP/HR/LegalForm', [
            'contract' => null,
            'contractTypes' => self::contractTypeOptions(),
            'defaultPasals' => [
                'website' => self::websitePasals(),
                'software_server' => self::softwareServerPasals(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateContract($request);
        $contract = LegalContract::create(array_merge($validated, [
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]));

        return redirect()->route('erp.hr.legal.edit', $contract)
            ->with('flash', ['type' => 'success', 'message' => 'Kontrak berhasil disimpan.']);
    }

    public function edit(LegalContract $legalContract): Response
    {
        return Inertia::render('ERP/HR/LegalForm', [
            'contract' => [
                'id' => $legalContract->id,
                'title' => $legalContract->title,
                'contract_number' => $legalContract->contract_number,
                'contract_date' => $legalContract->contract_date?->format('Y-m-d'),
                'contract_type' => $legalContract->contract_type,
                'pihak_pertama' => $legalContract->pihak_pertama,
                'pihak_kedua' => $legalContract->pihak_kedua,
                'pasals' => $legalContract->pasals,
                'status' => $legalContract->status,
            ],
            'contractTypes' => self::contractTypeOptions(),
            'defaultPasals' => [
                'website' => self::websitePasals(),
                'software_server' => self::softwareServerPasals(),
            ],
        ]);
    }

    public function update(Request $request, LegalContract $legalContract): RedirectResponse
    {
        $validated = $this->validateContract($request);
        $legalContract->update(array_merge($validated, ['updated_by' => Auth::id()]));

        return back()->with('flash', ['type' => 'success', 'message' => 'Kontrak berhasil diperbarui.']);
    }

    public function destroy(LegalContract $legalContract): RedirectResponse
    {
        $legalContract->delete();

        return redirect()->route('erp.hr.legal')
            ->with('flash', ['type' => 'success', 'message' => 'Kontrak berhasil dihapus.']);
    }

    public function pdf(LegalContract $legalContract): HttpResponse
    {
        $typeLabel = collect(self::contractTypeOptions())
            ->firstWhere('key', $legalContract->contract_type);

        $pdf = Pdf::loadView('pdf.legal-contract', [
            'contract' => $legalContract,
            'contractTypeLabel' => $typeLabel['label'] ?? $legalContract->contract_type,
            'pihakPertama' => $legalContract->pihak_pertama,
            'pihakKedua' => $legalContract->pihak_kedua,
            'pasals' => $legalContract->pasals ?? [],
        ])
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setPaper('a4', 'portrait');

        $filename = 'Kontrak-'.preg_replace('/[^A-Za-z0-9\-]/', '-', $legalContract->contract_number).'.pdf';

        return $pdf->download($filename);
    }

    public function downloadTemplate(string $file): BinaryFileResponse
    {
        $safeName = basename($file);
        $path = base_path('docs/'.$safeName);

        abort_unless(File::exists($path), 404);
        abort_unless(in_array(strtolower(pathinfo($safeName, PATHINFO_EXTENSION)), ['doc', 'docx'], true), 404);

        return response()->download($path, $safeName);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function validateContract(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'contract_number' => 'required|string|max:120',
            'contract_date' => 'required|date',
            'contract_type' => 'required|in:website,software_server',
            'pihak_pertama' => 'required|array',
            'pihak_pertama.name' => 'required|string|max:255',
            'pihak_pertama.address' => 'nullable|string|max:500',
            'pihak_pertama.phone' => 'nullable|string|max:60',
            'pihak_pertama.email' => 'nullable|string|max:255',
            'pihak_pertama.bank' => 'nullable|string|max:255',
            'pihak_kedua' => 'required|array',
            'pihak_kedua.name' => 'required|string|max:255',
            'pihak_kedua.address' => 'nullable|string|max:500',
            'pihak_kedua.phone' => 'nullable|string|max:60',
            'pihak_kedua.email' => 'nullable|string|max:255',
            'pihak_kedua.pic' => 'nullable|string|max:255',
            'pasals' => 'required|array|min:1',
            'pasals.*.title' => 'required|string|max:200',
            'pasals.*.content' => 'required|string|max:10000',
            'status' => 'required|in:draft,final',
        ]);
    }

    private static function contractTypeOptions(): array
    {
        return [
            ['key' => 'website', 'label' => 'Jasa Pembuatan Website'],
            ['key' => 'software_server', 'label' => 'Pengembangan Software & Sewa Server'],
        ];
    }

    private static function websitePasals(): array
    {
        return [
            [
                'title' => 'PASAL 1 – LINGKUP PEKERJAAN',
                'content' => "Pihak Pertama setuju untuk merancang, mengembangkan, dan mengimplementasikan website untuk Pihak Kedua dengan lingkup pekerjaan sebagai berikut:\n\nA. Jenis Website\n- Landing Page Company Profile\n- Halaman Shopping Cart / E-Commerce sederhana\n\nB. Teknologi yang Digunakan\n- Framework Backend: Laravel (PHP)\n- Database: MySQL\n- Frontend CSS Framework: Tailwind CSS\n- UI Component Library: DaisyUI\n- Deployment: Production Server (VPS / Shared Hosting sesuai kesepakatan)\n\nC. Fitur Company Profile\n- Halaman Beranda (Homepage) yang responsif dan modern\n- Halaman Tentang Kami (About Us)\n- Halaman Produk / Layanan\n- Halaman Portofolio / Galeri\n- Halaman Kontak (Contact Us) dengan form inquiry\n- Integrasi Google Maps / lokasi perusahaan\n- SEO On-Page dasar (meta title, meta description, sitemap)\n- Tampilan mobile-friendly / responsive\n\nD. Tidak Termasuk dalam Lingkup (Exclusions)\n- Integrasi payment gateway (Midtrans, Xendit, dll) – dapat ditambahkan sebagai fitur tambahan berbayar\n- Pembuatan konten teks dan foto produk (disediakan oleh Pihak Kedua)\n- Biaya domain dan hosting (ditanggung Pihak Kedua kecuali disepakati lain)\n- Pembuatan logo atau desain grafis brand identity",
            ],
            [
                'title' => 'PASAL 2 – NILAI KONTRAK DAN SKEMA PEMBAYARAN',
                'content' => "Nilai total kontrak jasa pembuatan website sebagaimana dimaksud dalam Pasal 1 adalah sebesar:\nRp ____________,- (_________________________ Rupiah)\n\nPembayaran dilakukan dalam tiga tahap sebagai berikut:\n1. Down Payment (DP) 35% – dibayar sebelum pengerjaan dimulai\n2. Pelunasan Utama 55% – dibayar saat website live / production\n3. Masa Garansi & Revisi 10% – dibayar setelah bugs & tambahan pasca-live selesai\n\nMetode Pembayaran:\n- Transfer Bank ke rekening Pihak Pertama\n- Pembayaran dinyatakan sah setelah bukti transfer dikirimkan kepada Pihak Pertama\n- Pengerjaan tidak akan dimulai sebelum DP diterima dan terkonfirmasi",
            ],
            [
                'title' => 'PASAL 3 – JANGKA WAKTU PENGERJAAN',
                'content' => "Estimasi jangka waktu pengerjaan adalah sebagai berikut:\n1. Briefing & Wireframe Design: 3-5 hari kerja\n2. Pengembangan Frontend (UI): 7-10 hari kerja\n3. Pengembangan Backend (Laravel): 7-10 hari kerja\n4. Integrasi & Testing Internal: 3-5 hari kerja\n5. Deployment & Go Live: 1-2 hari kerja\n6. Masa Garansi & Bug Fixing: 14 hari kalender\n\nEstimasi total waktu pengerjaan: 21-32 hari kerja sejak DP diterima.\nJangka waktu dapat berubah apabila terdapat keterlambatan dari Pihak Kedua dalam memberikan konten, feedback, atau dokumen yang dibutuhkan.",
            ],
            [
                'title' => 'PASAL 4 – HAK DAN KEWAJIBAN PARA PIHAK',
                'content' => "A. Kewajiban Pihak Pertama (Penyedia Jasa):\n- Mengerjakan proyek sesuai dengan lingkup pekerjaan yang disepakati dalam Pasal 1\n- Memberikan update progres pengerjaan secara berkala kepada Pihak Kedua\n- Menyelesaikan revisi yang wajar dan sesuai dengan brief awal\n- Melakukan deployment website ke server production\n- Memberikan garansi bug selama 14 (empat belas) hari kalender setelah website live\n- Menjaga kerahasiaan data dan informasi bisnis Pihak Kedua\n- Menyerahkan source code dan akses penuh kepada Pihak Kedua setelah pelunasan selesai\n\nB. Kewajiban Pihak Kedua (Klien):\n- Membayar biaya sesuai skema yang telah disepakati dalam Pasal 2\n- Menyediakan seluruh aset konten (teks, foto, logo, video) yang dibutuhkan tepat waktu\n- Memberikan feedback dan persetujuan (approval) dalam waktu 3 (tiga) hari kerja setelah setiap tahap selesai\n- Menyediakan akses domain dan hosting (jika diperlukan)\n- Menunjuk satu orang sebagai PIC (Person in Charge) untuk koordinasi proyek\n- Tidak melakukan perubahan atau pengeditan langsung pada sistem selama pengerjaan berlangsung",
            ],
            [
                'title' => 'PASAL 5 – REVISI DAN PERUBAHAN',
                'content' => "- Pihak Kedua berhak mendapatkan maksimal 3 (tiga) kali putaran revisi selama masa pengerjaan\n- Revisi yang dimaksud adalah perubahan yang masih dalam lingkup brief awal (minor revision)\n- Perubahan besar yang tidak termasuk dalam lingkup awal (major revision) akan dikenakan biaya tambahan yang disepakati bersama sebelum dikerjakan\n- Revisi dianggap major apabila mengubah struktur halaman, menambah fitur baru, atau mengubah total desain yang sudah disetujui",
            ],
            [
                'title' => 'PASAL 6 – GARANSI DAN PEMELIHARAAN',
                'content' => "Pihak Pertama memberikan garansi pasca-live dengan ketentuan:\n- Masa garansi berlaku selama 14 (empat belas) hari kalender terhitung sejak tanggal website live\n- Garansi mencakup perbaikan bug atau error yang ditemukan akibat kesalahan dari sisi pengerjaan (bukan akibat konten atau perubahan yang dilakukan Pihak Kedua)\n- Garansi TIDAK mencakup: penambahan fitur baru, perubahan desain, kerusakan akibat aksi pihak ketiga, atau masalah yang disebabkan oleh server/hosting Pihak Kedua\n- Pengembangan lanjutan setelah masa garansi berakhir dapat dilakukan dengan perjanjian kontrak baru atau sistem maintenance fee",
            ],
            [
                'title' => 'PASAL 7 – HAK KEKAYAAN INTELEKTUAL',
                'content' => "- Seluruh hak kekayaan intelektual atas website (source code, desain, konten yang dibuat oleh Pihak Pertama) akan berpindah sepenuhnya kepada Pihak Kedua setelah seluruh pembayaran dilunasi\n- Sebelum pelunasan penuh, Pihak Pertama berhak menahan penyerahan source code\n- Pihak Pertama berhak mencantumkan website tersebut sebagai bagian dari portofolio, kecuali Pihak Kedua secara tertulis melarang hal tersebut\n- Aset konten yang disediakan oleh Pihak Kedua (logo, foto, teks) tetap menjadi milik Pihak Kedua",
            ],
            [
                'title' => 'PASAL 8 – KETERLAMBATAN DAN PENALTI',
                'content' => "A. Keterlambatan oleh Pihak Pertama:\n- Jika Pihak Pertama terlambat menyelesaikan pekerjaan melebihi estimasi waktu yang disepakati tanpa alasan yang dapat diterima, Pihak Kedua berhak meminta kompensasi berupa pengerjaan tambahan gratis senilai maksimal 5% dari total kontrak\n- Keterlambatan akibat tidak tersedianya konten atau feedback dari Pihak Kedua tidak termasuk dalam kategori ini\n\nB. Keterlambatan oleh Pihak Kedua:\n- Jika Pihak Kedua terlambat memberikan konten, feedback, atau pembayaran lebih dari 7 (tujuh) hari kerja, maka timeline proyek akan dinyatakan otomatis mundur\n- Keterlambatan pembayaran akan dikenakan denda sebesar 2% per minggu dari jumlah yang tertunggak",
            ],
            [
                'title' => 'PASAL 9 – PEMBATALAN KONTRAK',
                'content' => "- Jika pembatalan dilakukan oleh Pihak Kedua sebelum pengerjaan dimulai, DP yang telah dibayarkan tidak dapat dikembalikan (non-refundable)\n- Jika pembatalan dilakukan setelah pengerjaan berjalan 50% atau lebih, Pihak Kedua wajib membayar 75% dari total nilai kontrak\n- Jika pembatalan dilakukan oleh Pihak Pertama karena force majeure, DP akan dikembalikan penuh\n- Pembatalan wajib dilakukan secara tertulis melalui surat atau pesan tertulis yang dapat didokumentasikan",
            ],
            [
                'title' => 'PASAL 10 – KERAHASIAAN',
                'content' => "Para Pihak sepakat untuk menjaga kerahasiaan seluruh informasi yang diperoleh selama berlangsungnya perjanjian ini, termasuk namun tidak terbatas pada strategi bisnis, data pelanggan, informasi keuangan, dan data teknis. Kewajiban ini tetap berlaku selama 2 (dua) tahun setelah perjanjian ini berakhir.",
            ],
            [
                'title' => 'PASAL 11 – PENYELESAIAN SENGKETA',
                'content' => "Apabila terjadi perselisihan antara Para Pihak terkait perjanjian ini, Para Pihak sepakat untuk menyelesaikannya melalui:\n1. Musyawarah mufakat antara Para Pihak dalam waktu 14 (empat belas) hari kalender\n2. Mediasi oleh pihak ketiga yang disepakati bersama jika musyawarah gagal\n3. Jalur hukum melalui Pengadilan Negeri yang berwenang jika mediasi tidak mencapai kesepakatan",
            ],
            [
                'title' => 'PASAL 12 – KETENTUAN LAIN-LAIN',
                'content' => "- Perjanjian ini merupakan keseluruhan kesepakatan antara Para Pihak dan menggantikan semua perjanjian atau diskusi sebelumnya terkait proyek yang sama\n- Setiap perubahan atau amandemen terhadap perjanjian ini harus dilakukan secara tertulis dan ditandatangani oleh kedua belah pihak\n- Jika salah satu ketentuan dalam perjanjian ini dinyatakan tidak sah, ketentuan lainnya tetap berlaku\n- Perjanjian ini dibuat dalam 2 (dua) rangkap bermaterai cukup, masing-masing mempunyai kekuatan hukum yang sama",
            ],
        ];
    }

    private static function softwareServerPasals(): array
    {
        return [
            [
                'title' => 'PASAL 1 – IDENTITAS PARA PIHAK',
                'content' => "Identitas para pihak telah tercantum pada bagian awal perjanjian ini. Para Pihak menyatakan bahwa data identitas yang diberikan adalah benar dan dapat dipertanggungjawabkan.",
            ],
            [
                'title' => 'PASAL 2 – RUANG LINGKUP PEKERJAAN',
                'content' => "Pihak Pertama setuju untuk mengembangkan sistem berbasis web dengan spesifikasi teknis sebagai berikut:\n\nJenis Proyek: ________________________________\nFrontend Framework: ________________________________\nUI Library: ________________________________\nDatabase: ________________________________\nPlatform: Web Application (Responsive)\n\nFitur-fitur yang akan dikembangkan meliputi namun tidak terbatas pada:\n- Manajemen data utama (tambah, edit, hapus, lihat detail)\n- Pencatatan dan laporan keuangan\n- Dashboard ringkasan\n- Fitur login dan autentikasi pengguna\n- Fitur-fitur lain sesuai kesepakatan",
            ],
            [
                'title' => 'PASAL 3 – NILAI KONTRAK DAN PEMBAYARAN',
                'content' => "3.1 Nilai Kontrak Pengembangan Sistem\nNilai total kontrak: Rp ____________,- (_________________________ Rupiah)\n\n3.2 Jadwal Pembayaran Pengembangan Sistem\n1. DP / Uang Muka (Tahap 1) – dibayar di awal sebelum pekerjaan dimulai: Rp ____________,-\n2. Pembayaran Tahap 2 – dibayar saat pekerjaan mencapai 50% progres: Rp ____________,-\n3. Pembayaran Pelunasan (Tahap 3) – dibayar setelah sistem dinyatakan selesai / LIVE: Rp ____________,-\n\n3.3 Biaya Sewa Server (jika ada)\nBiaya sewa server: Rp ____________,- per bulan / Rp ____________,- per tahun\n\n3.4 Ketentuan Pembayaran Sewa Server\n- Pembayaran sewa server dilakukan paling lambat tanggal 5 setiap bulannya\n- Keterlambatan lebih dari 7 hari dapat mengakibatkan penangguhan sementara layanan\n- Keterlambatan lebih dari 30 hari dapat mengakibatkan penghentian layanan",
            ],
            [
                'title' => 'PASAL 4 – WAKTU PENGERJAAN',
                'content' => "4.1. Estimasi waktu pengerjaan sistem adalah ______ (______) hari/minggu/bulan kalender terhitung sejak DP diterima oleh Pihak Pertama.\n4.2. Pihak Pertama berkewajiban memberikan laporan progres pengerjaan secara berkala kepada Pihak Kedua minimal setiap minggu.\n4.3. Apabila terjadi keterlambatan yang disebabkan oleh faktor di luar kendali Pihak Pertama (force majeure), maka waktu pengerjaan dapat diperpanjang atas kesepakatan kedua belah pihak.\n4.4. Keterlambatan yang disebabkan oleh kelalaian Pihak Pertama tanpa pemberitahuan dapat dikenakan denda sebesar ______ per hari keterlambatan.",
            ],
            [
                'title' => 'PASAL 5 – SERAH TERIMA DAN KRITERIA PENYELESAIAN',
                'content' => "5.1. Sistem dinyatakan selesai apabila telah memenuhi seluruh fitur yang disepakati dalam Pasal 2 dan dapat diakses secara online (LIVE).\n5.2. Pihak Pertama akan melakukan demo sistem kepada Pihak Kedua sebelum dinyatakan selesai.\n5.3. Pihak Kedua diberikan waktu uji coba selama ______ hari kalender setelah demo untuk melakukan pengujian sistem.\n5.4. Apabila dalam masa uji coba ditemukan bug atau kesalahan teknis, Pihak Pertama wajib memperbaiki dalam waktu ______ hari kerja tanpa biaya tambahan.\n5.5. Setelah Pihak Kedua menyatakan sistem telah selesai secara tertulis atau digital, maka proses pelunasan (Tahap 3) dapat dilakukan.",
            ],
            [
                'title' => 'PASAL 6 – HAK DAN KEWAJIBAN PARA PIHAK',
                'content' => "6.1 Kewajiban Pihak Pertama (Pengembang):\n- Mengerjakan sistem sesuai dengan ruang lingkup dan spesifikasi teknis yang disepakati\n- Menyerahkan source code dan dokumentasi teknis kepada Pihak Kedua setelah pelunasan diterima\n- Memberikan pelatihan singkat penggunaan sistem kepada Pihak Kedua setelah serah terima\n- Memberikan garansi perbaikan bug selama 1 (satu) bulan setelah sistem live\n- Menjaga kerahasiaan data Pihak Kedua\n\n6.2 Kewajiban Pihak Kedua (Klien):\n- Membayar biaya pengembangan sesuai jadwal yang telah disepakati dalam Pasal 3\n- Menyediakan data dan informasi yang diperlukan untuk pengembangan sistem secara tepat waktu\n- Memberikan feedback dan persetujuan desain dalam waktu yang wajar\n- Membayar biaya sewa server sesuai periode yang dipilih\n- Tidak memindahkan, menjual, atau memberikan lisensi sistem kepada pihak ketiga tanpa persetujuan Pihak Pertama",
            ],
            [
                'title' => 'PASAL 7 – KEPEMILIKAN DAN HAK KEKAYAAN INTELEKTUAL',
                'content' => "7.1. Setelah pelunasan selesai dilakukan, hak kepemilikan atas sistem secara penuh menjadi milik Pihak Kedua.\n7.2. Source code, database, dan seluruh aset digital yang dibuat untuk proyek ini akan diserahkan kepada Pihak Kedua setelah pelunasan.\n7.3. Pihak Pertama diperkenankan menggunakan sistem ini sebagai portofolio pengembangan, kecuali data sensitif Pihak Kedua.",
            ],
            [
                'title' => 'PASAL 8 – KERAHASIAAN',
                'content' => "8.1. Kedua belah pihak sepakat untuk menjaga kerahasiaan seluruh informasi yang diperoleh dalam rangka pelaksanaan perjanjian ini.\n8.2. Kewajiban kerahasiaan ini berlaku selama perjanjian berlangsung dan tetap berlaku selama 2 (dua) tahun setelah perjanjian berakhir.",
            ],
            [
                'title' => 'PASAL 9 – FORCE MAJEURE',
                'content' => "Yang dimaksud dengan Force Majeure dalam perjanjian ini adalah kejadian-kejadian di luar kemampuan para pihak yang tidak dapat diperkirakan sebelumnya, termasuk namun tidak terbatas pada: bencana alam, kebakaran, banjir, gempa bumi, pandemi, kerusuhan, pemadaman listrik berkepanjangan, atau gangguan infrastruktur internet. Dalam kondisi force majeure, pihak yang terkena dampak wajib memberitahukan kepada pihak lainnya dalam waktu 3 (tiga) hari kerja.",
            ],
            [
                'title' => 'PASAL 10 – PENYELESAIAN SENGKETA',
                'content' => "10.1. Apabila terjadi perselisihan antara para pihak, maka akan diselesaikan secara musyawarah untuk mufakat terlebih dahulu.\n10.2. Apabila penyelesaian secara musyawarah tidak tercapai dalam waktu 30 (tiga puluh) hari, maka para pihak sepakat untuk menyelesaikan perselisihan melalui Badan Arbitrase Nasional Indonesia (BANI) atau Pengadilan Negeri yang berwenang.",
            ],
            [
                'title' => 'PASAL 11 – KETENTUAN LAIN-LAIN',
                'content' => "11.1. Perjanjian ini berlaku sejak tanggal ditandatangani oleh kedua belah pihak.\n11.2. Segala perubahan atau penambahan terhadap perjanjian ini hanya sah apabila dibuat secara tertulis dan ditandatangani oleh kedua belah pihak.\n11.3. Perjanjian ini dibuat dalam 2 (dua) rangkap yang masing-masing memiliki kekuatan hukum yang sama, satu untuk Pihak Pertama dan satu untuk Pihak Kedua.",
            ],
        ];
    }
}
