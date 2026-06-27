<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = DocumentTemplate::query()
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'type', 'is_active', 'updated_at']);

        return Inertia::render('ERP/Settings/DocumentTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create(Request $request): Response
    {
        $type = $request->query('type', 'invoice');

        $defaults = match ($type) {
            'sales_note' => DocumentTemplate::defaultSalesNoteBlocks(),
            'pos_receipt' => DocumentTemplate::defaultPosReceiptBlocks(),
            default => DocumentTemplate::defaultInvoiceBlocks(),
        };

        return Inertia::render('ERP/Settings/DocumentTemplates/Builder', [
            'template' => null,
            'type' => $type,
            'defaultBlocks' => $defaults,
            'blockMeta' => $this->blockMeta($type),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:invoice,sales_note,pos_receipt',
            'blocks' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        $template = DocumentTemplate::query()->create($validated);

        return redirect()->route('erp.settings.document-templates.edit', $template)
            ->with('success', 'Template disimpan.');
    }

    public function edit(DocumentTemplate $documentTemplate): Response
    {
        return Inertia::render('ERP/Settings/DocumentTemplates/Builder', [
            'template' => $documentTemplate,
            'type' => $documentTemplate->type,
            'defaultBlocks' => $documentTemplate->blocks,
            'blockMeta' => $this->blockMeta($documentTemplate->type),
        ]);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'blocks' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        $documentTemplate->update($validated);

        return back()->with('success', 'Template diperbarui.');
    }

    public function destroy(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $documentTemplate->delete();

        return redirect()->route('erp.settings.document-templates.index')
            ->with('success', 'Template dihapus.');
    }

    public function activate(DocumentTemplate $documentTemplate): RedirectResponse
    {
        DocumentTemplate::query()
            ->where('type', $documentTemplate->type)
            ->update(['is_active' => false]);

        $documentTemplate->update(['is_active' => true]);

        return back()->with('success', 'Template diaktifkan.');
    }

    public function duplicate(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $copy = $documentTemplate->replicate();
        $copy->name = $documentTemplate->name.' (Salinan)';
        $copy->is_active = false;
        $copy->save();

        return redirect()->route('erp.settings.document-templates.edit', $copy)
            ->with('success', 'Template diduplikasi.');
    }

    public function preview(Request $request): \Illuminate\Http\Response
    {
        $type = $request->query('type', 'invoice');
        $blocks = $request->input('blocks', []);
        $settings = $request->input('settings', []);

        if (empty($blocks)) {
            $blocks = match ($type) {
                'sales_note' => DocumentTemplate::defaultSalesNoteBlocks(),
                'pos_receipt' => DocumentTemplate::defaultPosReceiptBlocks(),
                default => DocumentTemplate::defaultInvoiceBlocks(),
            };
        }

        $mockProject = (object) [
            'name' => 'Project Demo CCTV Kantor',
            'client_name' => 'PT Maju Bersama',
            'client_contact' => 'demo@majubersama.co.id',
            'payments' => collect(),
        ];

        $mockInvoice = [
            'number' => 'INV-PRJ-000001',
            'status' => 'partial',
            'amount' => 5000000,
            'paid_amount' => 2000000,
            'remaining_amount' => 3000000,
        ];

        $mockItems = collect([
            ['name' => 'Kamera CCTV 4MP Dome', 'qty' => 4, 'uom' => 'unit', 'unit_price' => 800000, 'subtotal' => 3200000],
            ['name' => 'NVR 8 Channel',        'qty' => 1, 'uom' => 'unit', 'unit_price' => 1200000, 'subtotal' => 1200000],
            ['name' => 'Jasa Instalasi',       'qty' => 1, 'uom' => 'paket', 'unit_price' => 600000, 'subtotal' => 600000],
        ]);

        $html = view('pdf.document-template', [
            'docType' => 'invoice',
            'docTitle' => match ($type) {
                'sales_note' => 'NOTA PENJUALAN', default => 'INVOICE'
            },
            'docSubtitle' => match ($type) {
                'sales_note' => 'Lampiran Item Penjualan', default => 'Dokumen Tagihan Project'
            },
            'docNumberLabel' => match ($type) {
                'sales_note' => 'No. Nota', default => 'Nomor'
            },
            'docMetaTitle' => match ($type) {
                'sales_note' => 'Data Dokumen', default => 'Informasi Invoice'
            },
            'templateBlocks' => $blocks,
            'templateSettings' => $settings,
            'project' => $mockProject,
            'invoice' => $mockInvoice,
            'items' => $mockItems,
            'itemsSubtotal' => $mockItems->sum('subtotal'),
            'totalDiscount' => 0,
            'cashReceived' => 2000000,
            'taxAmount' => 0,
            'brand' => ['name' => 'OCN ERP Suite', 'tagline' => 'Integrated Business Platform', 'logo_data_uri' => null],
            'generatedAt' => now(),
        ])->render();

        $pageSizes = [
            'a4' => ['w' => 794, 'h' => 1123, 'pad' => 57],
            'a5' => ['w' => 794, 'h' => 559,  'pad' => 40],  // A5 landscape
            'letter' => ['w' => 816, 'h' => 1056, 'pad' => 57],
            'legal' => ['w' => 816, 'h' => 1344, 'pad' => 57],
        ];
        $size = $pageSizes[$request->input('page_size', 'a4')] ?? $pageSizes['a4'];

        $wrapped = '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                html, body { background: #e2e8f0; width: 100%; min-height: 100%; font-family: DejaVu Sans, sans-serif; }
                .page { background: #fff; width: '.$size['w'].'px; min-height: '.$size['h'].'px;
                    margin: 24px auto; padding: '.$size['pad'].'px;
                    box-shadow: 0 2px 16px rgba(0,0,0,.15); }
            </style></head><body>
            <div class="page">'.$html.'</div></body></html>';

        return response($wrapped, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function blockMeta(string $type): array
    {
        $invoiceBlocks = [
            ['type' => 'header',      'label' => 'Header & Logo',     'icon' => 'photo',          'fields' => [
                ['key' => 'show_logo',    'label' => 'Tampilkan logo',    'type' => 'toggle'],
                ['key' => 'show_tagline', 'label' => 'Tampilkan tagline', 'type' => 'toggle'],
                ['key' => 'title',        'label' => 'Judul dokumen',     'type' => 'text'],
                ['key' => 'subtitle',     'label' => 'Sub judul',         'type' => 'text'],
                ['key' => 'accent_color', 'label' => 'Warna aksen',       'type' => 'color'],
            ]],
            ['type' => 'doc_meta',    'label' => 'Info Dokumen',      'icon' => 'document-text',  'fields' => [
                ['key' => 'show_number', 'label' => 'No. Dokumen', 'type' => 'toggle'],
                ['key' => 'show_date',   'label' => 'Tanggal',     'type' => 'toggle'],
                ['key' => 'show_status', 'label' => 'Status',      'type' => 'toggle'],
            ]],
            ['type' => 'client_info', 'label' => 'Info Customer',     'icon' => 'user',           'fields' => [
                ['key' => 'label',        'label' => 'Label section',  'type' => 'text'],
                ['key' => 'show_contact', 'label' => 'Tampilkan kontak', 'type' => 'toggle'],
            ]],
            ['type' => 'items_table', 'label' => 'Tabel Item',        'icon' => 'table-cells',    'fields' => [
                ['key' => 'show_no',         'label' => 'Kolom No.',         'type' => 'toggle'],
                ['key' => 'show_uom',        'label' => 'Kolom Satuan',      'type' => 'toggle'],
                ['key' => 'show_unit_price', 'label' => 'Kolom Harga Satuan', 'type' => 'toggle'],
            ]],
            ['type' => 'totals',      'label' => 'Ringkasan Total',   'icon' => 'calculator',     'fields' => [
                ['key' => 'show_subtotal',  'label' => 'Subtotal',      'type' => 'toggle'],
                ['key' => 'show_tax',       'label' => 'PPN',           'type' => 'toggle'],
                ['key' => 'show_discount',  'label' => 'Diskon',        'type' => 'toggle'],
                ['key' => 'show_paid',      'label' => 'Sudah Dibayar', 'type' => 'toggle'],
                ['key' => 'show_remaining', 'label' => 'Sisa Tagihan',  'type' => 'toggle'],
                ['key' => 'label_total',    'label' => 'Label baris total', 'type' => 'text'],
            ]],
            ['type' => 'payment_terms', 'label' => 'Termin Pembayaran', 'icon' => 'calendar',       'fields' => []],
            ['type' => 'notes',       'label' => 'Catatan',           'icon' => 'pencil-square',  'fields' => [
                ['key' => 'text', 'label' => 'Isi catatan', 'type' => 'textarea'],
            ]],
            ['type' => 'signature',   'label' => 'Tanda Tangan',      'icon' => 'pen',            'fields' => [
                ['key' => 'label',            'label' => 'Label atas', 'type' => 'text'],
                ['key' => 'name_placeholder', 'label' => 'Nama / Jabatan', 'type' => 'text'],
            ]],
            ['type' => 'footer',      'label' => 'Footer',            'icon' => 'bars-3-bottom-left', 'fields' => [
                ['key' => 'show_print_date', 'label' => 'Tampilkan tgl cetak', 'type' => 'toggle'],
                ['key' => 'text',            'label' => 'Teks footer custom',  'type' => 'text'],
            ]],
        ];

        $posBlocks = [
            ['type' => 'store_header',     'label' => 'Header Toko',       'icon' => 'building-storefront', 'fields' => [
                ['key' => 'show_address', 'label' => 'Alamat toko',  'type' => 'toggle'],
                ['key' => 'show_phone',   'label' => 'Nomor telp',   'type' => 'toggle'],
            ]],
            ['type' => 'transaction_info', 'label' => 'Info Transaksi',    'icon' => 'receipt-percent',     'fields' => [
                ['key' => 'show_cashier', 'label' => 'Nama kasir',   'type' => 'toggle'],
                ['key' => 'show_channel', 'label' => 'Channel bayar', 'type' => 'toggle'],
            ]],
            ['type' => 'items',            'label' => 'Item Transaksi',    'icon' => 'list-bullet',          'fields' => [
                ['key' => 'show_sku', 'label' => 'Tampilkan SKU', 'type' => 'toggle'],
            ]],
            ['type' => 'totals',           'label' => 'Ringkasan Total',   'icon' => 'calculator',           'fields' => [
                ['key' => 'show_discount', 'label' => 'Diskon',    'type' => 'toggle'],
                ['key' => 'show_tax',      'label' => 'Pajak',     'type' => 'toggle'],
                ['key' => 'show_change',   'label' => 'Kembalian', 'type' => 'toggle'],
            ]],
            ['type' => 'payment_info',     'label' => 'Info Pembayaran',   'icon' => 'credit-card',          'fields' => [
                ['key' => 'show_method', 'label' => 'Metode bayar', 'type' => 'toggle'],
            ]],
            ['type' => 'footer_message',   'label' => 'Pesan Footer',      'icon' => 'chat-bubble-bottom-center-text', 'fields' => [
                ['key' => 'text', 'label' => 'Teks pesan', 'type' => 'textarea'],
            ]],
        ];

        return match ($type) {
            'pos_receipt' => $posBlocks,
            default => $invoiceBlocks,
        };
    }
}
