<?php

namespace App\Http\Controllers;

use App\Services\GeneratedFileArchiveService;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly GeneratedFileArchiveService $generatedFileArchiveService,
    ) {}

    public function show(string|int $id): Response
    {
        Gate::allowIf(fn ($user) => $user->hasPermissionTo('erp.sales.manage'));

        $payload = $this->invoiceService->getInvoiceDocument($id);
        $invoice = $payload['invoice'];

        return $this->makePdf('pdf.invoice', $payload)
            ->stream('invoice-'.$invoice['number'].'.pdf');
    }

    public function download(string|int $id): Response
    {
        Gate::allowIf(fn ($user) => $user->hasPermissionTo('erp.sales.manage'));

        $payload = $this->invoiceService->getInvoiceDocument($id);
        $invoice = $payload['invoice'];

        return $this->generatedFileArchiveService->downloadPdf(
            $this->makePdf('pdf.invoice', $payload),
            'invoice-'.$invoice['number'].'.pdf',
        );
    }

    public function showSalesNote(string|int $id): Response
    {
        Gate::allowIf(fn ($user) => $user->hasPermissionTo('erp.sales.manage'));

        $payload = $this->invoiceService->getInvoiceDocument($id);
        $invoice = $payload['invoice'];

        return $this->makePdf('pdf.sales-note', $payload)
            ->stream('nota-'.$invoice['number'].'.pdf');
    }

    public function downloadSalesNote(string|int $id): Response
    {
        Gate::allowIf(fn ($user) => $user->hasPermissionTo('erp.sales.manage'));

        $payload = $this->invoiceService->getInvoiceDocument($id);
        $invoice = $payload['invoice'];

        return $this->generatedFileArchiveService->downloadPdf(
            $this->makePdf('pdf.sales-note', $payload),
            'nota-'.$invoice['number'].'.pdf',
        );
    }

    private function makePdf(string $view, array $data): DompdfWrapper
    {
        return Pdf::loadView($view, $data)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'dpi' => 150,
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'chroot' => public_path(),
            ]);
    }
}
