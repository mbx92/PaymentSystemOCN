<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentTemplateService
{
    /** Map template page_size key → [dompdf paper, orientation] */
    private const PAPER_MAP = [
        'a4' => ['a4',     'portrait'],
        'a5' => ['a5',     'landscape'],
        'letter' => ['letter', 'portrait'],
        'legal' => ['legal',  'portrait'],
    ];

    public function resolveBlocks(string $type): array
    {
        $template = DocumentTemplate::activeFor($type);

        if ($template) {
            return [$template->blocks, $template->settings ?? []];
        }

        return match ($type) {
            'sales_note' => [DocumentTemplate::defaultSalesNoteBlocks(), []],
            'pos_receipt' => [DocumentTemplate::defaultPosReceiptBlocks(), []],
            default => [DocumentTemplate::defaultInvoiceBlocks(), []],
        };
    }

    private function applyPaper(\Barryvdh\DomPDF\PDF $pdf, array $settings): \Barryvdh\DomPDF\PDF
    {
        $key = $settings['page_size'] ?? 'a4';
        [$paper, $orientation] = self::PAPER_MAP[$key] ?? ['a4', 'portrait'];

        return $pdf->setPaper($paper, $orientation);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function renderInvoicePdf(array $data): \Barryvdh\DomPDF\PDF
    {
        [$blocks, $settings] = $this->resolveBlocks('invoice');

        $pdf = Pdf::loadView('pdf.document-template', array_merge($data, [
            'docType' => 'invoice',
            'docTitle' => 'INVOICE',
            'docSubtitle' => 'Dokumen Tagihan Project',
            'docNumberLabel' => 'Nomor',
            'docMetaTitle' => 'Informasi Invoice',
            'templateBlocks' => $blocks,
            'templateSettings' => $settings,
        ]));

        return $this->applyPaper($pdf, $settings);
    }

    public function renderSalesNotePdf(array $data): \Barryvdh\DomPDF\PDF
    {
        [$blocks, $settings] = $this->resolveBlocks('sales_note');

        $pdf = Pdf::loadView('pdf.document-template', array_merge($data, [
            'docType' => 'invoice',
            'docTitle' => 'NOTA PENJUALAN',
            'docSubtitle' => 'Lampiran Item Penjualan',
            'docNumberLabel' => 'No. Nota',
            'docMetaTitle' => 'Data Dokumen',
            'templateBlocks' => $blocks,
            'templateSettings' => $settings,
        ]));

        return $this->applyPaper($pdf, $settings);
    }
}
