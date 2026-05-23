<?php

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {
    }

    public function show(string|int $id): Response
    {
        $payload = $this->invoiceService->getInvoiceDocument($id);
        $invoice = $payload['invoice'];

        return $this->makePdf('pdf.invoice', $payload)
            ->stream('invoice-'.$invoice['number'].'.pdf');
    }

    public function download(string|int $id): Response
    {
        $payload = $this->invoiceService->getInvoiceDocument($id);
        $invoice = $payload['invoice'];

        return $this->makePdf('pdf.invoice', $payload)
            ->download('invoice-'.$invoice['number'].'.pdf');
    }

    public function showSalesNote(string|int $id): Response
    {
        $payload = $this->invoiceService->getInvoiceDocument($id);
        $invoice = $payload['invoice'];

        return $this->makePdf('pdf.sales-note', $payload)
            ->stream('nota-'.$invoice['number'].'.pdf');
    }

    public function downloadSalesNote(string|int $id): Response
    {
        $payload = $this->invoiceService->getInvoiceDocument($id);
        $invoice = $payload['invoice'];

        return $this->makePdf('pdf.sales-note', $payload)
            ->download('nota-'.$invoice['number'].'.pdf');
    }

    private function makePdf(string $view, array $data): DompdfWrapper
    {
        return Pdf::loadView($view, $data)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'dpi' => 150,
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
            ]);
    }
}
