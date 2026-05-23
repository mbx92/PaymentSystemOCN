<?php

namespace App\Http\Controllers;

use App\Models\CashIn;
use App\Models\ErpSetting;
use App\Models\Project;
use App\Models\ProjectPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectPaymentController extends Controller
{
    public function markPaid(Request $request, ProjectPayment $payment)
    {
        $validated = $request->validate([
            'paid_at' => 'required|date',
            'note'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($payment, $validated) {
            $payment->update($validated);

            $noteParts = [
                'Pembayaran termin '.$payment->term_number.' ('.$payment->percentage.'%)',
            ];
            if (! empty($validated['note'])) {
                $noteParts[] = $validated['note'];
            }
            $cashNote = implode(' — ', $noteParts);

            CashIn::updateOrCreate(
                ['project_payment_id' => $payment->id],
                [
                    'project_id'  => $payment->project_id,
                    'category'    => 'pendapatan_project',
                    'amount'      => $payment->amount,
                    'date'        => $validated['paid_at'],
                    'note'        => $cashNote,
                    'created_by'  => Auth::id(),
                ]
            );
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Termin berhasil ditandai lunas dan dicatat di kas masuk.']);
    }

    public function markUnpaid(ProjectPayment $payment)
    {
        DB::transaction(function () use ($payment) {
            CashIn::where('project_payment_id', $payment->id)->delete();
            $payment->update(['paid_at' => null, 'note' => null]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Status termin diubah ke belum lunas; entri kas masuk terkait dihapus.']);
    }

    public function downloadInvoice(Project $project)
    {
        $project->loadMissing(['payments', 'cashIns', 'materials.product', 'convertedBudget.items']);
        $project->loadSum('cashIns as paid_amount', 'amount');

        $lineItems = $project->resolveInvoiceLineItems();
        $paidAmount = (float) ($project->paid_amount ?? 0);
        $amount = (float) $project->resolveInvoiceAmount();
        $remaining = max($amount - $paidAmount, 0);

        $pdf = Pdf::loadView('pdf.project-invoice', [
            'project' => $project,
            'invoice' => [
                'id' => $project->id,
                'number' => $project->invoice_number
                    ?: ('INV-PRJ-'.($project->finished_at?->format('Ymd') ?? $project->created_at?->format('Ymd') ?? now()->format('Ymd')).'-'.strtoupper(substr(str_replace('-', '', (string) $project->getKey()), -6))),
                'project' => $project->name,
                'client' => $project->client_name,
                'amount' => $amount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remaining,
                'status' => $remaining <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
                'finished_at' => $project->finished_at?->format('Y-m-d'),
                'created_at' => $project->created_at?->format('Y-m-d'),
            ],
            'lineItems' => $lineItems,
            'lineItemsSubtotal' => $lineItems->sum('subtotal'),
            'brand' => $this->pdfBrand(),
            'generatedAt' => now(),
        ])->setPaper('a4');

        $filename = 'Invoice-Payment-'.str_replace(' ', '-', $project->name).'-'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    private function pdfBrand(): array
    {
        $setting     = ErpSetting::query()->first();
        $logoDataUri = null;

        if ($setting?->app_logo_path && Storage::disk('public')->exists($setting->app_logo_path)) {
            $path        = Storage::disk('public')->path($setting->app_logo_path);
            $mime        = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';
            $logoDataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
        }

        return [
            'name'          => (string) ($setting?->app_name ?: 'OCN ERP Suite'),
            'tagline'       => (string) ($setting?->app_tagline ?: 'Integrated Business Platform'),
            'logo_data_uri' => $logoDataUri,
        ];
    }
}
