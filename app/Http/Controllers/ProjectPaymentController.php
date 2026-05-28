<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Core\Services\FiscalPeriodService;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CashIn;
use App\Models\CategoryCoaMapping;
use App\Models\ErpSetting;
use App\Models\Project;
use App\Models\ProjectPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectPaymentController extends Controller
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {}

    public function markPaid(Request $request, ProjectPayment $payment)
    {
        $validated = $request->validate([
            'paid_at'         => 'required|date',
            'note'            => 'nullable|string',
            'cash_account_id' => Account::cashBankIdValidationRules(),
        ]);

        $companyId = ErpCompanyResolver::resolveForGlPosting($request);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['paid_at'], $companyId, 'paid_at', 'Pembayaran termin');

        DB::transaction(function () use ($payment, $validated, $companyId) {
            $payment->update([
                'paid_at' => $validated['paid_at'],
                'note'    => $validated['note'] ?? null,
            ]);

            $noteParts = [
                'Pembayaran termin '.$payment->term_number.' ('.$payment->percentage.'%)',
            ];
            if (! empty($validated['note'])) {
                $noteParts[] = $validated['note'];
            }
            $cashNote = implode(' — ', $noteParts);

            // Resolve akun kas dan akun pendapatan project dari CoA mapping
            $cashAccountId = (int) $validated['cash_account_id'];
            $revenueAccountId = $this->resolvePendapatanProjectAccountId();

            $cashAccount    = Account::query()->findOrFail($cashAccountId);
            $revenueAccount = Account::query()->findOrFail($revenueAccountId);

            // Jika sudah ada CashIn (re-mark paid), reverse journal lama terlebih dahulu
            $existingCashIn = CashIn::query()
                ->where('project_payment_id', $payment->id)
                ->first();

            if ($existingCashIn && $existingCashIn->journal_entry_id) {
                $this->reverseJournalEntry($existingCashIn->journal_entry_id);
            }

            $cashIn = CashIn::updateOrCreate(
                ['project_payment_id' => $payment->id],
                [
                    'project_id'      => $payment->project_id,
                    'cash_account_id' => $cashAccountId,
                    'category'        => 'pendapatan_project',
                    'amount'          => $payment->amount,
                    'date'            => $validated['paid_at'],
                    'note'            => $cashNote,
                    'created_by'      => Auth::id(),
                    'document_status' => DocumentStatus::Posted->value,
                    'approved_at'     => now(),
                    'approved_by'     => Auth::id(),
                    'posted_at'       => now(),
                    'posted_by'       => Auth::id(),
                ]
            );

            // Post ke General Ledger: DR Kas | CR Pendapatan Project
            $entry = $this->glPostingService->post(
                $companyId,
                sourceModule: 'cash_in',
                sourceReference: (string) $cashIn->id,
                description: 'Termin '.$payment->term_number.' project '.$payment->project_id.' — '.$cashNote,
                entryDate: $validated['paid_at'],
                lines: [
                    ['account_id' => $cashAccount->id,    'debit' => (float) $payment->amount, 'credit' => 0],
                    ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => (float) $payment->amount],
                ]
            );

            $cashIn->update(['journal_entry_id' => $entry->id]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Termin berhasil ditandai lunas dan dicatat di kas masuk & jurnal GL.']);
    }

    public function markUnpaid(ProjectPayment $payment)
    {
        DB::transaction(function () use ($payment) {
            $cashIn = CashIn::query()
                ->where('project_payment_id', $payment->id)
                ->first();

            if ($cashIn) {
                // Reverse journal entry jika ada sebelum delete CashIn
                if ($cashIn->journal_entry_id) {
                    $this->reverseJournalEntry($cashIn->journal_entry_id);
                }

                $cashIn->delete();
            }

            $payment->update(['paid_at' => null, 'note' => null]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Status termin diubah ke belum lunas; kas masuk dan jurnal GL terkait telah di-reverse.']);
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

    /**
     * Resolve account_id untuk kategori pendapatan_project dari CategoryCoaMapping.
     * Fallback: cari akun dengan code yang mengandung 'pendapatan' atau tipe revenue.
     */
    private function resolvePendapatanProjectAccountId(): int
    {
        $accountId = CategoryCoaMapping::query()
            ->where('domain', 'cash_in')
            ->where('category', 'pendapatan_project')
            ->value('account_id');

        if (! $accountId) {
            throw ValidationException::withMessages([
                'cash_account_id' => 'Kategori pendapatan_project belum di-mapping ke akun CoA. Silakan atur di menu Pengaturan CoA.',
            ]);
        }

        return (int) $accountId;
    }

    /**
     * Reverse journal entry dengan membalik semua debit <-> credit pada journal lines.
     * Ini membuat efek net = 0 (cancellation) tanpa menghapus record histori.
     */
    private function reverseJournalEntry(int $journalEntryId): void
    {
        $entry = \App\ERP\Accounting\Models\JournalEntry::query()
            ->with('lines')
            ->find($journalEntryId);

        if (! $entry) {
            return;
        }

        foreach ($entry->lines as $line) {
            $debit  = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);

            $line->update([
                'debit'  => number_format($credit, 2, '.', ''),
                'credit' => number_format($debit, 2, '.', ''),
            ]);
        }
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
