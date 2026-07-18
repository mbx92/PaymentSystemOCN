<?php

namespace App\Http\Controllers;

use App\Models\RndProject;
use App\Services\GeneratedFileArchiveService;
use App\Services\PdfThemeResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RndReportController extends Controller
{
    public function __construct(
        private readonly PdfThemeResolver $pdfThemeResolver,
        private readonly GeneratedFileArchiveService $generatedFileArchiveService,
    ) {}

    public function show(Request $request, RndProject $rndProject): Response
    {
        $rndProject->loadMissing(['picUser:id,name', 'budgetItems', 'productOutputs', 'purchases.product:id,sku,name,uom', 'purchases.supplier:id,code,name']);

        $purchases = $rndProject->purchases()
            ->with(['product:id,sku,name,uom', 'supplier:id,code,name'])
            ->paginate($this->resolvedPerPage($request), ['*'], 'report_purchases_page')
            ->withQueryString()
            ->through(fn ($purchase): array => [
                'id' => $purchase->id,
                'product_name' => $purchase->product?->name,
                'product_sku' => $purchase->product?->sku,
                'supplier_name' => $purchase->supplier?->name,
                'qty' => (float) $purchase->qty,
                'uom' => $purchase->product?->uom,
                'unit_price' => (float) $purchase->unit_price,
                'total_price' => (float) $purchase->total_price,
                'category' => $purchase->category,
                'purchase_date' => $purchase->purchase_date?->toDateString(),
                'notes' => $purchase->notes,
                'receipt_url' => $purchase->receipt_path ? route('storage.serve', ['path' => $purchase->receipt_path]) : null,
            ]);

        return Inertia::render('Rnd/Report', [
            'project' => [
                'id' => $rndProject->id,
                'name' => $rndProject->name,
                'category' => $rndProject->category,
                'status' => $rndProject->status,
                'description' => $rndProject->description,
                'notes' => $rndProject->notes,
                'pic_name' => $rndProject->picUser?->name,
                'start_date' => $rndProject->start_date?->toDateString(),
            ],
            'summary' => [
                'estimated_budget_total' => $rndProject->estimated_budget_total_value,
                'actual_spend_total' => $rndProject->actual_spend_total_value,
                'alat_total' => $rndProject->alat_total_value,
                'bahan_total' => $rndProject->bahan_total_value,
                'variance' => $rndProject->variance_value,
                'units_produced_total' => $rndProject->units_produced_total_value,
                'hpp_per_unit' => $rndProject->hpp_per_unit_value,
            ],
            'budgetItems' => $rndProject->budgetItems->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'qty' => (float) $item->qty,
                'estimated_unit_price' => (float) $item->estimated_unit_price,
                'total_price' => (float) $item->total_price,
            ])->values(),
            'outputs' => $rndProject->productOutputs->map(fn ($output): array => [
                'id' => $output->id,
                'name' => $output->name,
                'description' => $output->description,
                'units_produced' => (float) $output->units_produced,
                'notes' => $output->notes,
                'hpp_per_unit' => $rndProject->hpp_per_unit_value,
                'allocated_cost' => $rndProject->hpp_per_unit_value * (float) $output->units_produced,
            ])->values(),
            'purchases' => $purchases,
        ]);
    }

    public function pdf(RndProject $rndProject)
    {
        $rndProject->loadMissing(['picUser:id,name', 'budgetItems', 'productOutputs', 'purchases.product:id,sku,name,uom', 'purchases.supplier:id,code,name']);

        $pdf = Pdf::loadView('pdf.rnd-project-report', [
            'project' => $rndProject,
            'theme' => $this->pdfThemeResolver->theme(),
            'brand' => $this->pdfThemeResolver->brand(),
            'company' => $this->pdfThemeResolver->companyContact(),
            'summary' => [
                'estimated_budget_total' => $rndProject->estimated_budget_total_value,
                'actual_spend_total' => $rndProject->actual_spend_total_value,
                'alat_total' => $rndProject->alat_total_value,
                'bahan_total' => $rndProject->bahan_total_value,
                'variance' => $rndProject->variance_value,
                'units_produced_total' => $rndProject->units_produced_total_value,
                'hpp_per_unit' => $rndProject->hpp_per_unit_value,
            ],
            'generatedAt' => now(),
        ]);

        return $this->generatedFileArchiveService->downloadPdf(
            $pdf,
            'rnd-report-'.$rndProject->id.'.pdf',
        );
    }
}
