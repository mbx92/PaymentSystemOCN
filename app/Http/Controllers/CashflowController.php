<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Accounting\Support\CashAccountLabelResolver;
use App\ERP\Accounting\Services\CoaSettingService;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Core\Services\FiscalPeriodService;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\AccountingInventoryRecord;
use App\Models\CashCategory;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\CategoryCoaMapping;
use App\Models\PaymentMethod;
use App\Models\PosSale;
use App\Models\Project;
use App\Models\TeamDistribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CashflowController extends Controller
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {}

    public function index(Request $request): Response
    {
        $entries = $this->buildEntries($request);
        $paginatedEntries = $this->paginateEntries($entries, $request);
        $cashInTotal = (float) $entries->where('type', 'in')->sum('amount');
        $cashOutTotal = (float) $entries->where('type', 'out')->sum('amount');

        return Inertia::render('ERP/Accounting/Cashflow', [
            'entries' => $paginatedEntries,
            'totals' => [
                'cash_in' => $cashInTotal,
                'cash_out' => $cashOutTotal,
                'net' => $cashInTotal - $cashOutTotal,
            ],
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'cashAccounts' => Account::cashBankOptions(),
            'filters' => $this->filtersWithPerPage($request, ['type', 'source', 'project_id', 'category', 'company_id', 'date_from', 'date_to', 'q']),
            'sourceOptions' => $this->sourceOptions(),
            'categoryOptions' => [
                'in' => $this->cashflowCategoryOptionsFor('cash_in'),
                'out' => $this->cashflowCategoryOptionsFor('cash_out'),
            ],
            'canMutate' => $this->canMutateCashflow($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out',
            'project_id' => 'nullable|uuid|exists:projects,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'recipient_name' => 'nullable|string|max:255',
        ]);
        $validated['project_id'] = null;

        if ($validated['type'] === 'in') {
            $this->storeCashInEntry($request, $validated);

            return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil ditambahkan.']);
        }

        $this->storeCashOutEntry($request, $validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil ditambahkan.']);
    }

    public function updateCashIn(Request $request, CashIn $cashIn): RedirectResponse
    {
        $this->authorizeMutation($cashIn->created_by);
        $companyId = $this->resolvedCashInCompanyId($cashIn);
        $this->fiscalPeriodService->ensureDateIsOpen($cashIn->date ?? now(), $companyId, 'date', 'Perubahan kas masuk');
        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);
        unset($validated['project_id']);
        $this->assertCategoryExists('cash_in', $validated['category']);
        $revenueAccountId = $this->resolveMappedAccountId('cash_in', $validated['category']);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Perubahan kas masuk');

        DB::transaction(function () use ($cashIn, $validated, $revenueAccountId, $companyId): void {
            // Reverse jurnal lama sebelum buat jurnal baru (reverse-and-repost)
            if ($cashIn->journal_entry_id) {
                $this->reverseJournalEntryLines($cashIn->journal_entry_id);
            }

            $cashIn->update($validated);

            $cashAccount    = Account::query()->findOrFail((int) $validated['cash_account_id']);
            $revenueAccount = Account::query()->findOrFail($revenueAccountId);

            $newEntry = $this->glPostingService->post(
                $companyId,
                sourceModule: 'cash_in',
                sourceReference: (string) $cashIn->id,
                description: 'Koreksi kas masuk '.$cashIn->id,
                entryDate: $validated['date'],
                lines: [
                    ['account_id' => $cashAccount->id,    'debit' => $validated['amount'], 'credit' => 0],
                    ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $validated['amount']],
                ]
            );

            $cashIn->update(['journal_entry_id' => $newEntry->id]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil diperbarui dan jurnal GL telah disinkronkan.']);
    }

    public function updateCashOut(Request $request, CashOut $cashOut): RedirectResponse
    {
        $this->authorizeMutation($cashOut->created_by);
        $companyId = $this->resolvedCashOutCompanyId($cashOut);
        $this->fiscalPeriodService->ensureDateIsOpen($cashOut->date ?? now(), $companyId, 'date', 'Perubahan kas keluar');
        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'recipient_name' => 'nullable|string|max:255',
        ]);
        unset($validated['project_id']);
        $this->assertCategoryExists('cash_out', $validated['category']);
        $expenseAccountId = $this->resolveMappedAccountId('cash_out', $validated['category']);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Perubahan kas keluar');

        DB::transaction(function () use ($cashOut, $validated, $expenseAccountId, $companyId): void {
            // Reverse jurnal lama sebelum buat jurnal baru (reverse-and-repost)
            if ($cashOut->journal_entry_id) {
                $this->reverseJournalEntryLines($cashOut->journal_entry_id);
            }

            $cashOut->update($validated);

            $expenseAccount = Account::query()->findOrFail($expenseAccountId);
            $cashAccount    = Account::query()->findOrFail((int) $validated['cash_account_id']);

            $newEntry = $this->glPostingService->post(
                $companyId,
                sourceModule: 'cash_out',
                sourceReference: (string) $cashOut->id,
                description: 'Koreksi kas keluar '.$cashOut->id,
                entryDate: $validated['date'],
                lines: [
                    ['account_id' => $expenseAccount->id, 'debit' => $validated['amount'], 'credit' => 0],
                    ['account_id' => $cashAccount->id,    'debit' => 0, 'credit' => $validated['amount']],
                ]
            );

            $cashOut->update(['journal_entry_id' => $newEntry->id]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil diperbarui dan jurnal GL telah disinkronkan.']);
    }

    public function destroyCashIn(CashIn $cashIn): RedirectResponse
    {
        $this->authorizeMutation($cashIn->created_by);
        $companyId = $this->resolvedCashInCompanyId($cashIn);
        $this->fiscalPeriodService->ensureDateIsOpen($cashIn->date ?? now(), $companyId, 'date', 'Penghapusan kas masuk');

        DB::transaction(function () use ($cashIn): void {
            if ($cashIn->journal_entry_id) {
                $this->reverseJournalEntryLines($cashIn->journal_entry_id);
            }
            $cashIn->delete();
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil dihapus dan jurnal GL telah di-reverse.']);
    }

    public function destroyCashOut(CashOut $cashOut): RedirectResponse
    {
        $this->authorizeMutation($cashOut->created_by);
        $companyId = $this->resolvedCashOutCompanyId($cashOut);
        $this->fiscalPeriodService->ensureDateIsOpen($cashOut->date ?? now(), $companyId, 'date', 'Penghapusan kas keluar');

        DB::transaction(function () use ($cashOut): void {
            if ($cashOut->journal_entry_id) {
                $this->reverseJournalEntryLines($cashOut->journal_entry_id);
            }
            $cashOut->delete();
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil dihapus dan jurnal GL telah di-reverse.']);
    }

    /**
     * Reverse (balik debit <-> credit) semua lines pada journal entry yang diberikan.
     * Digunakan sebelum update (reverse-and-repost) dan sebelum delete.
     */
    private function reverseJournalEntryLines(int $journalEntryId): void
    {
        $entry = JournalEntry::query()->with('lines')->find($journalEntryId);

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

    private function buildEntries(Request $request): Collection
    {
        $source = $this->sourceFilter($request);
        $companyId = ErpCompanyResolver::resolveForReporting($request);
        $memberPaymentCashOutIds = TeamDistribution::query()
            ->whereNotNull('cash_out_id')
            ->pluck('cash_out_id');
        $memberPaymentByCashOutId = TeamDistribution::query()
            ->with(['user:id,name', 'project:id,name'])
            ->whereIn('cash_out_id', $memberPaymentCashOutIds)
            ->get()
            ->keyBy('cash_out_id');

        $cashIns = in_array($source, ['pos', 'supplier_payment', 'member_payment', 'inventaris'], true)
            ? collect()
            : CashIn::query()
                ->with(['project:id,name', 'creator:id,name', 'paymentMethod:id,name', 'cashAccount:id,code,name'])
                ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn ($jq) => $jq->where('company_id', $companyId)))
                ->when($source === 'project', fn ($q) => $q->whereNotNull('project_id'))
                ->when($source === 'manual', fn ($q) => $q->whereNull('project_id'))
                ->when($request->filled('project_id') && Str::isUuid($request->string('project_id')->toString()), fn ($q) => $q->where('project_id', $request->string('project_id')->toString()))
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('date', '>=', $request->date('date_from')))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('date', '<=', $request->date('date_to')))
                ->get();

        $cashInDebitAccounts = $this->debitCashAccountsByJournal($cashIns->pluck('journal_entry_id')->filter());

        $cashIns = $cashIns->map(fn (CashIn $entry) => [
                    'id' => $entry->id,
                    'type' => 'in',
                    'source' => $entry->project_id ? 'project' : 'manual',
                    'source_name' => $entry->project_payment_id
                        ? 'Termin Project'
                        : ($entry->category === 'pendapatan_project' ? 'Invoice Project' : 'Masuk Manual'),
                    'reference_no' => $entry->project_payment_id ? 'TERM-'.$entry->project_payment_id : (string) $entry->id,
                    'date' => $entry->date?->format('Y-m-d'),
                    'project_name' => $entry->project?->name ?? '-',
                    'project_id' => $entry->project_id,
                    'category' => $entry->category,
                    'amount' => (float) $entry->amount,
                    'payment_method_name' => $entry->paymentMethod?->name,
                    'payment_method_id' => $entry->payment_method_id,
                    'cash_account_id' => $entry->cash_account_id,
                    'cash_account_name' => CashAccountLabelResolver::label(
                        $entry->cashAccount,
                        $entry->journal_entry_id,
                        $cashInDebitAccounts
                    ),
                    'recipient_name' => null,
                    'note' => $entry->note,
                    'creator_name' => $entry->creator?->name ?? '-',
                    'document_status' => $entry->document_status,
                    'journal_entry_id' => $entry->journal_entry_id,
                    'mutable' => true,
                    'created_at' => optional($entry->created_at)->timestamp ?? 0,
                ]);

        $cashOuts = in_array($source, ['pos', 'supplier_payment', 'inventaris'], true)
            ? collect()
            : CashOut::query()
                ->with(['project:id,name', 'creator:id,name', 'cashAccount:id,code,name'])
                ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn ($jq) => $jq->where('company_id', $companyId)))
                ->when($source === 'member_payment', fn ($q) => $q->whereIn('id', $memberPaymentCashOutIds))
                ->when($source === 'project', fn ($q) => $q
                    ->whereNotNull('project_id')
                    ->whereNotIn('id', $memberPaymentCashOutIds))
                ->when($source === 'manual', fn ($q) => $q
                    ->whereNull('project_id')
                    ->whereNotIn('id', $memberPaymentCashOutIds))
                ->when($request->filled('project_id') && Str::isUuid($request->string('project_id')->toString()), fn ($q) => $q->where('project_id', $request->string('project_id')->toString()))
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('date', '>=', $request->date('date_from')))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('date', '<=', $request->date('date_to')))
                ->get();

        $cashOutDebitAccounts = $this->debitCashAccountsByJournal($cashOuts->pluck('journal_entry_id')->filter());

        $cashOuts = $cashOuts->map(function (CashOut $entry) use ($memberPaymentByCashOutId, $cashOutDebitAccounts) {
                    $distribution = $memberPaymentByCashOutId->get($entry->id);
                    $isMemberPayment = $distribution !== null;

                    return [
                    'id' => $entry->id,
                    'type' => 'out',
                    'source' => $isMemberPayment ? 'member_payment' : ($entry->project_id ? 'project' : 'manual'),
                    'source_name' => $isMemberPayment ? 'Pembayaran Anggota' : 'Expense',
                    'reference_no' => $isMemberPayment
                        ? 'ANGGOTA-'.strtoupper(substr((string) $distribution->id, 0, 8))
                        : (string) $entry->id,
                    'date' => $entry->date?->format('Y-m-d'),
                    'project_name' => $entry->project?->name ?? '-',
                    'project_id' => $entry->project_id,
                    'category' => $entry->category,
                    'amount' => (float) $entry->amount,
                    'payment_method_name' => null,
                    'payment_method_id' => null,
                    'cash_account_id' => $entry->cash_account_id,
                    'cash_account_name' => CashAccountLabelResolver::label(
                        $entry->cashAccount,
                        $entry->journal_entry_id,
                        $cashOutDebitAccounts
                    ),
                    'recipient_name' => $entry->recipient_name,
                    'note' => $entry->note,
                    'creator_name' => $entry->creator?->name ?? '-',
                    'document_status' => $entry->document_status,
                    'journal_entry_id' => $entry->journal_entry_id,
                    'mutable' => ! $isMemberPayment,
                    'created_at' => optional($entry->created_at)->timestamp ?? 0,
                ];
                });

        $supplierPayments = $source !== '' && $source !== 'supplier_payment'
            ? collect()
            : PayablePayment::query()
                ->with(['payable.vendor:id,name', 'cashAccount:id,code,name', 'payer:id,name'])
                ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn ($jq) => $jq->where('company_id', $companyId)))
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->date('date_from')))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->date('date_to')))
                ->get();

        $supplierDebitAccounts = $this->debitCashAccountsByJournal($supplierPayments->pluck('journal_entry_id')->filter());

        $supplierPayments = $supplierPayments->map(fn (PayablePayment $payment) => [
                    'id' => 'ap-'.$payment->id,
                    'type' => 'out',
                    'source' => 'supplier_payment',
                    'source_name' => 'Pembayaran Supplier',
                    'reference_no' => $payment->payable?->bill_no ?? (string) $payment->id,
                    'date' => $payment->payment_date?->format('Y-m-d'),
                    'project_name' => '-',
                    'project_id' => null,
                    'category' => 'pembayaran_hutang_supplier',
                    'amount' => (float) $payment->amount,
                    'payment_method_name' => null,
                    'payment_method_id' => null,
                    'cash_account_id' => $payment->cash_account_id,
                    'cash_account_name' => CashAccountLabelResolver::label(
                        $payment->cashAccount,
                        $payment->journal_entry_id,
                        $supplierDebitAccounts
                    ),
                    'recipient_name' => $payment->payable?->vendor?->name,
                    'note' => $payment->note,
                    'creator_name' => $payment->payer?->name ?? '-',
                    'document_status' => DocumentStatus::Posted->value,
                    'journal_entry_id' => $payment->journal_entry_id,
                    'mutable' => false,
                    'created_at' => optional($payment->created_at)->timestamp ?? 0,
                ]);

        $inventoryOutflows = $source !== '' && $source !== 'inventaris'
            ? collect()
            : AccountingInventoryRecord::query()
                ->with(['cashAccount:id,code,name', 'assetAccount:id,code,name', 'creator:id,name'])
                ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn ($jq) => $jq->where('company_id', $companyId)))
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('acquisition_date', '>=', $request->date('date_from')))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('acquisition_date', '<=', $request->date('date_to')))
                ->get()
                ->map(fn (AccountingInventoryRecord $record) => [
                    'id' => 'inv-'.$record->id,
                    'type' => 'out',
                    'source' => 'inventaris',
                    'source_name' => 'Inventaris',
                    'reference_no' => 'INV-'.$record->id,
                    'date' => $record->acquisition_date?->format('Y-m-d'),
                    'project_name' => '-',
                    'project_id' => null,
                    'category' => 'pembelian_inventaris',
                    'amount' => (float) $record->amount,
                    'payment_method_name' => null,
                    'payment_method_id' => null,
                    'cash_account_id' => $record->cash_account_id,
                    'cash_account_name' => $record->cashAccount?->displayLabel() ?? '-',
                    'recipient_name' => $record->item_name,
                    'note' => trim(collect([
                        $record->assetAccount?->displayLabel(),
                        $record->note,
                    ])->filter()->implode(' · ')) ?: null,
                    'creator_name' => $record->creator?->name ?? '-',
                    'document_status' => DocumentStatus::Posted->value,
                    'journal_entry_id' => $record->journal_entry_id,
                    'mutable' => false,
                    'created_at' => optional($record->created_at)->timestamp ?? 0,
                ]);

        $posCashAccount = app(CoaSettingService::class)->resolveAccountByKey('pos_sale_cash_account', '1001');
        $posSales = $this->buildPosEntries($request, $posCashAccount);

        $merged = collect($cashIns)->merge($cashOuts)
            ->merge($supplierPayments)
            ->merge($inventoryOutflows)
            ->merge($posSales)
            ->sortByDesc(fn (array $row) => sprintf('%s-%d', $row['date'] ?? '0000-00-00', $row['created_at']));

        $filtered = $merged;
        if ($request->filled('type')) {
            $filtered = $filtered->where('type', $request->string('type')->toString());
        }

        if ($request->filled('q')) {
            $term = mb_strtolower($request->string('q')->toString());
            $filtered = $filtered->filter(function (array $row) use ($term): bool {
                $haystacks = [
                    $row['project_name'],
                    $row['source_name'] ?? null,
                    $row['reference_no'] ?? null,
                    $row['category'],
                    $row['payment_method_name'],
                    $row['cash_account_name'],
                    $row['recipient_name'],
                    $row['note'],
                    $row['creator_name'],
                    (string) ($row['journal_entry_id'] ?? ''),
                ];

                foreach ($haystacks as $value) {
                    if ($value && str_contains(mb_strtolower((string) $value), $term)) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $filtered->values();
    }

    private function paginateEntries(Collection $entries, Request $request, string $pageName = 'page'): LengthAwarePaginator
    {
        $perPage = $this->resolvedPerPage($request);
        $currentPage = Paginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator(
            $entries->forPage($currentPage, $perPage)->values()->all(),
            $entries->count(),
            $perPage,
            $currentPage,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
                'query' => $request->query(),
            ],
        );
    }

    private function buildPosEntries(Request $request, Account $posCashAccount): Collection
    {
        $source = $this->sourceFilter($request);
        if ($request->filled('project_id') || in_array($source, ['project', 'manual', 'member_payment'], true)) {
            return collect();
        }

        $companyId = ErpCompanyResolver::resolveForReporting($request);

        $sales = PosSale::query()
            ->with(['paymentMethod:id,name', 'soldBy:id,name'])
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('sold_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('sold_at', '<=', $request->date('date_to')))
            ->get();

        if ($sales->isEmpty()) {
            return collect();
        }

        $journalMap = JournalEntry::query()
            ->whereIn('source_module', ['pos_sale', 'pos_sale_refund', 'pos_sale_reopen'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('source_reference', $sales->pluck('number')->all())
            ->get(['id', 'source_module', 'source_reference'])
            ->keyBy(fn (JournalEntry $entry) => $entry->source_module.'|'.$entry->source_reference);

        return $sales->map(function (PosSale $sale) use ($posCashAccount, $journalMap, $companyId): ?array {
            $status = (string) $sale->status;
            $type = $status === 'refunded' ? 'out' : 'in';
            $category = $status === 'refunded' ? 'refund_penjualan_pos' : 'penjualan_pos';
            $sourceModule = match ($status) {
                'refunded' => 'pos_sale_refund',
                'reopened' => 'pos_sale_reopen',
                default => 'pos_sale',
            };
            $journalEntry = $journalMap->get($sourceModule.'|'.$sale->number);

            if ($companyId && ! $journalEntry) {
                return null;
            }

            return [
                'id' => 'pos-'.$sale->id,
                'type' => $type,
                'source' => 'pos',
                'source_name' => 'POS',
                'reference_no' => $sale->number,
                'date' => $sale->sold_at?->format('Y-m-d'),
                'project_name' => 'POS',
                'project_id' => null,
                'category' => $category,
                'amount' => (float) $sale->grand_total,
                'payment_method_name' => $type === 'in' ? ($sale->paymentMethod?->name ?? '-') : null,
                'payment_method_id' => $type === 'in' ? $sale->payment_method_id : null,
                'cash_account_id' => $posCashAccount->id,
                'cash_account_name' => $posCashAccount->code.' - '.$posCashAccount->name,
                'recipient_name' => $type === 'out' ? 'Customer Refund POS' : null,
                'note' => trim(collect([
                    'Transaksi POS '.$sale->number,
                    $status !== 'paid' ? 'Status '.strtoupper($status) : null,
                    $sale->note,
                ])->filter()->implode(' | ')),
                'creator_name' => $sale->soldBy?->name ?? '-',
                'document_status' => DocumentStatus::Posted->value,
                'journal_entry_id' => $journalEntry?->id,
                'mutable' => false,
                'created_at' => optional($sale->sold_at)->timestamp ?? 0,
            ];
        })->filter()->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|string|null>  $journalEntryIds
     * @return \Illuminate\Support\Collection<int, JournalLine>
     */
    private function debitCashAccountsByJournal(Collection $journalEntryIds): Collection
    {
        $ids = $journalEntryIds->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return JournalLine::query()
            ->with('account:id,code,name')
            ->whereIn('journal_entry_id', $ids)
            ->where('debit', '>', 0)
            ->get()
            ->keyBy('journal_entry_id');
    }

    private function canMutateCashflow(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->hasAnyRole(['admin', 'manajer'])
            || $user?->can('erp.accounting.post-journal'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{value: string, label: string}>
     */
    private function cashflowCategoryOptionsFor(string $domain): Collection
    {
        $baseOptions = CashCategory::query()
            ->where('domain', $domain)
            ->whereNotIn('key', CashCategory::retiredKeysFor($domain))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['key', 'label'])
            ->map(fn ($category) => [
                'value' => (string) $category->key,
                'label' => (string) $category->label,
            ]);

        $syntheticOptions = collect(match ($domain) {
            'cash_out' => [
                ['value' => 'pembayaran_hutang_supplier', 'label' => 'Pembayaran Hutang Supplier'],
                ['value' => 'pembelian_inventaris', 'label' => 'Pembelian Inventaris'],
            ],
            default => [],
        });

        return $baseOptions
            ->concat($syntheticOptions)
            ->unique('value')
            ->values();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function sourceOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Semua Sumber'],
            ['value' => 'project', 'label' => 'Project'],
            ['value' => 'pos', 'label' => 'POS'],
            ['value' => 'manual', 'label' => 'Manual / Umum'],
            ['value' => 'supplier_payment', 'label' => 'Pembayaran Supplier'],
            ['value' => 'member_payment', 'label' => 'Pembayaran Anggota'],
            ['value' => 'inventaris', 'label' => 'Inventaris'],
        ];
    }

    private function sourceFilter(Request $request): string
    {
        $source = $request->string('source')->toString();

        return in_array($source, ['project', 'pos', 'manual', 'supplier_payment', 'member_payment', 'inventaris'], true) ? $source : '';
    }

    private function storeCashInEntry(Request $request, array $validated): void
    {
        $this->assertCategoryExists('cash_in', $validated['category']);
        $revenueAccountId = $this->resolveMappedAccountId('cash_in', $validated['category']);
        $companyId = ErpCompanyResolver::resolveForGlPosting($request);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Posting kas masuk');

        DB::transaction(function () use ($validated, $revenueAccountId, $companyId): void {
            $payload = [
                'project_id' => $validated['project_id'] ?? null,
                'payment_method_id' => $validated['payment_method_id'] ?? null,
                'cash_account_id' => $validated['cash_account_id'],
                'category' => $validated['category'],
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
                'document_status' => DocumentStatus::Posted->value,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ];

            $cashIn = CashIn::query()->create($payload);
            $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);
            $revenueAccount = Account::query()->findOrFail($revenueAccountId);
            $entry = $this->glPostingService->post(
                $companyId,
                sourceModule: 'cash_in',
                sourceReference: (string) $cashIn->id,
                description: 'Kas masuk proyek '.$cashIn->project_id,
                entryDate: $payload['date'],
                lines: [
                    ['account_id' => $cashAccount->id, 'debit' => $payload['amount'], 'credit' => 0],
                    ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $payload['amount']],
                ]
            );

            $cashIn->update(['journal_entry_id' => $entry->id]);
        });
    }

    private function storeCashOutEntry(Request $request, array $validated): void
    {
        $this->assertCategoryExists('cash_out', $validated['category']);
        $expenseAccountId = $this->resolveMappedAccountId('cash_out', $validated['category']);
        $companyId = ErpCompanyResolver::resolveForGlPosting($request);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Posting kas keluar');

        DB::transaction(function () use ($validated, $expenseAccountId, $companyId): void {
            $payload = [
                'project_id' => $validated['project_id'] ?? null,
                'cash_account_id' => $validated['cash_account_id'],
                'category' => $validated['category'],
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
                'recipient_name' => $validated['recipient_name'] ?? null,
                'created_by' => Auth::id(),
                'document_status' => DocumentStatus::Posted->value,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ];

            $cashOut = CashOut::query()->create($payload);
            $expenseAccount = Account::query()->findOrFail($expenseAccountId);
            $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);
            $entry = $this->glPostingService->post(
                $companyId,
                sourceModule: 'cash_out',
                sourceReference: (string) $cashOut->id,
                description: 'Kas keluar proyek '.$cashOut->project_id,
                entryDate: $payload['date'],
                lines: [
                    ['account_id' => $expenseAccount->id, 'debit' => $payload['amount'], 'credit' => 0],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $payload['amount']],
                ]
            );

            $cashOut->update(['journal_entry_id' => $entry->id]);
        });
    }

    private function assertCategoryExists(string $domain, string $category): void
    {
        if (CashCategory::isRetired($domain, $category)) {
            throw ValidationException::withMessages([
                'category' => 'Kategori ini sudah tidak digunakan untuk transaksi baru.',
            ]);
        }

        $exists = CashCategory::query()
            ->where('domain', $domain)
            ->where('key', $category)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'category' => 'Kategori tidak valid atau nonaktif.',
            ]);
        }
    }

    private function resolveMappedAccountId(string $domain, string $category): int
    {
        $accountId = CategoryCoaMapping::query()
            ->where('domain', $domain)
            ->where('category', $category)
            ->value('account_id');

        if (! $accountId) {
            throw ValidationException::withMessages([
                'category' => 'Kategori belum di-mapping ke akun CoA.',
            ]);
        }

        return (int) $accountId;
    }

    private function authorizeMutation(?int $creatorId): void
    {
        $user = Auth::user();
        if ($user?->hasRole('admin')) {
            return;
        }

        if ($creatorId && (int) $creatorId === (int) Auth::id()) {
            return;
        }

        abort(403, 'Anda tidak memiliki izin untuk mengubah transaksi ini.');
    }

    private function resolvedCashInCompanyId(CashIn $cashIn): ?int
    {
        $cashIn->loadMissing(['journalEntry:id,company_id', 'creator:id,company_id']);

        return $cashIn->journalEntry?->company_id ?? $cashIn->creator?->company_id;
    }

    private function resolvedCashOutCompanyId(CashOut $cashOut): ?int
    {
        $cashOut->loadMissing(['journalEntry:id,company_id', 'creator:id,company_id']);

        return $cashOut->journalEntry?->company_id ?? $cashOut->creator?->company_id;
    }
}
