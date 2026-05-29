<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Accounting\Services\CashAccountIdBackfillService;
use App\ERP\Accounting\Services\CashAccountReassignmentService;
use App\ERP\Accounting\Services\CoaSettingService;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Accounting\Services\JournalEntrySideReversalService;
use App\ERP\Accounting\Services\SupplierPaymentCompanySyncService;
use App\ERP\Core\Models\Company;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Purchasing\Models\GoodsReceipt;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Jobs\RebuildInventoryStockJob;
use App\Models\CategoryCoaMapping;
use App\Services\CogsBackfillService;
use App\Services\ProjectMaterialBackfillService;
use App\Services\ProjectMaterialReservationService;
use App\Services\WarehouseStockRebuildService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ERPAccountingUtilityController extends Controller
{
    private const POS_SALE_MODULES = ['pos_sale', 'pos_sale_reopen'];

    public function index(Request $request): Response
    {
        $companies = Company::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name', 'is_active']);

        $query = JournalEntry::query()
            ->with('company:id,name')
            ->withSum('lines as debit_total', 'debit')
            ->withSum('lines as credit_total', 'credit')
            ->latest('entry_date')
            ->latest('id');

        if ($request->filled('company_id') && $request->string('company_id')->toString() !== 'all') {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('entry_date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->where('entry_date', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($inner) use ($term): void {
                $inner->where('entry_no', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%')
                    ->orWhere('source_module', 'like', '%'.$term.'%');
            });
        }

        $entries = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'entry_no' => $entry->entry_no,
                'entry_date' => $entry->entry_date?->format('Y-m-d'),
                'description' => $entry->description,
                'source_module' => $entry->source_module,
                'source_reference' => $entry->source_reference,
                'company_id' => $entry->company_id,
                'company_name' => $entry->company?->name ?? 'Belum ditentukan',
                'debit_total' => (float) ($entry->debit_total ?? 0),
                'credit_total' => (float) ($entry->credit_total ?? 0),
            ]);

        $companySummaries = JournalEntry::query()
            ->leftJoin('companies', 'companies.id', '=', 'journal_entries.company_id')
            ->selectRaw('COALESCE(companies.name, ?) as company_name', ['Belum ditentukan'])
            ->selectRaw('journal_entries.company_id')
            ->selectRaw('COUNT(*) as entry_count')
            ->groupBy('journal_entries.company_id', 'companies.name')
            ->orderBy('companies.name')
            ->get()
            ->map(fn ($row) => [
                'company_id' => $row->company_id,
                'company_name' => $row->company_name,
                'entry_count' => (int) $row->entry_count,
            ]);

        return Inertia::render('ERP/Accounting/Utilities', [
            'companies' => $companies,
            'entries' => $entries,
            'companySummaries' => $companySummaries,
            'filters' => $this->filtersWithPerPage($request, ['company_id', 'date_from', 'date_to', 'q']),
            'posChannelCorrection' => $this->posChannelCorrectionSummary($request),
            'cashAccountBackfill' => app(CashAccountIdBackfillService::class)->summary(),
            'cashBankAccounts' => Account::cashBankOptions()->map(fn (Account $account) => [
                'id' => $account->id,
                'label' => $account->displayLabel(),
            ])->values(),
            'supplierPaymentCompanySync' => $this->supplierPaymentCompanySyncSummary($request),
            'cashAccountUsage' => app(CashAccountReassignmentService::class)->countsBySourceAccount(),
            'cashAccountReassignment' => $this->cashAccountReassignmentPreview($request),
            'inventoryReservationSync' => $this->inventoryReservationSyncSummary(),
            'inventoryStockRebuild' => $this->inventoryStockRebuildSummary(),
            'poExpenseReclassify' => $this->poExpenseReclassifySummary(),
            'cogsBackfill' => app(CogsBackfillService::class)->summarize(),
            'materialCogsBackfill' => app(ProjectMaterialBackfillService::class)->summarize(),
        ]);
    }

    public function syncInventoryReservations(): RedirectResponse
    {
        $result = app(ProjectMaterialReservationService::class)->syncAllWarehouseReservations();

        return back()->with('flash', [
            'type' => $result['warehouse_rows_updated'] > 0 ? 'success' : 'info',
            'message' => $result['warehouse_rows_updated'] > 0
                ? "Reserved stock disinkronkan. {$result['warehouse_rows_updated']} baris gudang diperbarui, {$result['warehouse_rows_cleared']} baris reserve lama dibersihkan."
                : 'Tidak ada perubahan reserved stock. Data sudah sinkron.',
        ]);
    }

    public function rebuildInventoryStocks(): RedirectResponse
    {
        RebuildInventoryStockJob::dispatch();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Rebuild stok warehouse telah dijadwalkan ke antrian (queue) dan akan diproses secara bertahap.',
        ]);
    }

    public function reassignCashAccounts(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_account_id' => Account::cashBankIdValidationRules(),
            'to_account_id' => Account::cashBankIdValidationRules(),
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $fromAccountId = (int) $validated['from_account_id'];
        $toAccountId = (int) $validated['to_account_id'];

        if ($fromAccountId === $toAccountId) {
            throw ValidationException::withMessages([
                'to_account_id' => 'Akun tujuan harus berbeda dari akun sumber.',
            ]);
        }

        $result = app(CashAccountReassignmentService::class)->apply(
            $fromAccountId,
            $toAccountId,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        $total = $result['cash_in_updated'] + $result['cash_out_updated'];

        return back()->with('flash', [
            'type' => $total > 0 ? 'success' : 'info',
            'message' => $total > 0
                ? "Akun kas/bank dipindahkan: {$result['cash_in_updated']} kas masuk, {$result['cash_out_updated']} kas keluar, {$result['journal_lines_updated']} baris jurnal."
                : 'Tidak ada transaksi yang cocok dengan filter.',
        ]);
    }

    public function backfillCashAccountIds(): RedirectResponse
    {
        $result = app(CashAccountIdBackfillService::class)->apply();

        $total = $result['cash_in_updated'] + $result['cash_out_updated'];

        return back()->with('flash', [
            'type' => $total > 0 ? 'success' : 'info',
            'message' => $total > 0
                ? "Akun kas diperbarui: {$result['cash_in_updated']} kas masuk, {$result['cash_out_updated']} kas keluar."
                : 'Tidak ada transaksi kas masuk/keluar yang perlu diperbaiki.',
        ]);
    }

    public function syncSupplierPaymentCompanies(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $result = app(SupplierPaymentCompanySyncService::class)->apply(
            isset($validated['company_id']) ? (int) $validated['company_id'] : null,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        return back()->with('flash', [
            'type' => $result['entry_count'] > 0 ? 'success' : 'info',
            'message' => $result['entry_count'] > 0
                ? "{$result['entry_count']} jurnal pembayaran supplier disinkronkan ke usaha asal dokumen hutangnya."
                : 'Tidak ada jurnal pembayaran supplier yang perlu disinkronkan.',
        ]);
    }

    public function moveJournalEntries(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_company_id' => ['required', 'integer', 'exists:companies,id'],
            'journal_entry_ids' => ['required', 'array', 'min:1'],
            'journal_entry_ids.*' => ['integer', 'exists:journal_entries,id'],
        ]);

        $targetCompanyId = (int) $validated['target_company_id'];
        $journalEntryIds = collect($validated['journal_entry_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $movedCount = DB::transaction(function () use ($journalEntryIds, $targetCompanyId): int {
            return JournalEntry::query()
                ->whereIn('id', $journalEntryIds)
                ->where(function ($query) use ($targetCompanyId): void {
                    $query->where('company_id', '!=', $targetCompanyId)
                        ->orWhereNull('company_id');
                })
                ->update(['company_id' => $targetCompanyId]);
        });

        $target = Company::query()->find($targetCompanyId);

        return back()->with('flash', [
            'type' => 'success',
            'message' => $movedCount.' transaksi accounting dipindahkan ke '.($target?->name ?? 'usaha tujuan').'.',
        ]);
    }

    public function reverseJournalEntrySides(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'journal_entry_ids' => ['required', 'array', 'min:1'],
            'journal_entry_ids.*' => ['integer', 'exists:journal_entries,id'],
        ]);

        $result = app(JournalEntrySideReversalService::class)->apply(
            collect($validated['journal_entry_ids'])
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );

        return back()->with('flash', [
            'type' => 'success',
            'message' => "{$result['entry_count']} jurnal dibalik sisi debit/kreditnya. {$result['line_count']} baris jurnal diperbarui.",
        ]);
    }

    public function correctPosChannelPayable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'journal_entry_ids' => ['required', 'array', 'min:1'],
            'journal_entry_ids.*' => ['integer', 'exists:journal_entries,id'],
        ]);

        [$expenseAccount, $payableAccount] = $this->posChannelCorrectionAccounts();

        $journalEntryIds = collect($validated['journal_entry_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $correctedCount = DB::transaction(function () use ($journalEntryIds, $expenseAccount, $payableAccount): int {
            $lines = JournalLine::query()
                ->with(['journalEntry.lines'])
                ->whereIn('journal_entry_id', $journalEntryIds)
                ->where('account_id', $expenseAccount->id)
                ->where('credit', '>', 0)
                ->whereHas('journalEntry', fn ($entry) => $entry->whereIn('source_module', self::POS_SALE_MODULES))
                ->get();

            $count = 0;
            foreach ($lines as $line) {
                $matchingDebit = $line->journalEntry?->lines
                    ->contains(fn (JournalLine $candidate): bool => (int) $candidate->account_id === (int) $expenseAccount->id
                        && (float) $candidate->debit > 0
                        && abs((float) $candidate->debit - (float) $line->credit) < 0.01);

                if (! $matchingDebit) {
                    continue;
                }

                $line->update(['account_id' => $payableAccount->id]);
                $count++;
            }

            return $count;
        });

        return back()->with('flash', [
            'type' => $correctedCount > 0 ? 'success' : 'warning',
            'message' => $correctedCount > 0
                ? $correctedCount.' baris kredit biaya admin channel dikoreksi ke '.$payableAccount->code.' - '.$payableAccount->name.'.'
                : 'Tidak ada baris jurnal yang cocok untuk dikoreksi.',
        ]);
    }

    private function posChannelCorrectionSummary(Request $request): array
    {
        try {
            [$expenseAccount, $payableAccount] = $this->posChannelCorrectionAccounts();
        } catch (ValidationException $exception) {
            return [
                'can_correct' => false,
                'message' => collect($exception->errors())->flatten()->first(),
                'candidate_count' => 0,
            ];
        }

        $candidates = $this->posChannelCorrectionCandidates($request, $expenseAccount);
        $candidateCount = $candidates->sum('candidate_count');

        return [
            'can_correct' => true,
            'expense_account' => $this->accountLabel($expenseAccount),
            'payable_account' => $this->accountLabel($payableAccount),
            'candidate_count' => $candidateCount,
            'candidates' => $candidates->take(25)->values(),
        ];
    }

    private function posChannelCorrectionCandidates(Request $request, Account $expenseAccount)
    {
        $query = JournalEntry::query()
            ->with(['company:id,name', 'lines.account:id,code,name'])
            ->whereIn('source_module', self::POS_SALE_MODULES)
            ->whereHas('lines', fn ($line) => $line
                ->where('account_id', $expenseAccount->id)
                ->where('credit', '>', 0));

        $this->applyJournalEntryFilters($query, $request);

        return $query
            ->latest('entry_date')
            ->latest('id')
            ->limit(500)
            ->get()
            ->map(function (JournalEntry $entry) use ($expenseAccount): ?array {
                $creditLines = $entry->lines
                    ->filter(fn (JournalLine $line): bool => (int) $line->account_id === (int) $expenseAccount->id && (float) $line->credit > 0)
                    ->filter(fn (JournalLine $line): bool => $entry->lines->contains(
                        fn (JournalLine $candidate): bool => (int) $candidate->account_id === (int) $expenseAccount->id
                            && (float) $candidate->debit > 0
                            && abs((float) $candidate->debit - (float) $line->credit) < 0.01
                    ));

                if ($creditLines->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $entry->id,
                    'entry_no' => $entry->entry_no,
                    'entry_date' => $entry->entry_date?->format('Y-m-d'),
                    'description' => $entry->description,
                    'source_module' => $entry->source_module,
                    'source_reference' => $entry->source_reference,
                    'company_name' => $entry->company?->name ?? 'Belum ditentukan',
                    'candidate_count' => $creditLines->count(),
                    'candidate_amount' => (float) $creditLines->sum('credit'),
                ];
            })
            ->filter()
            ->values();
    }

    private function applyJournalEntryFilters($query, Request $request): void
    {
        if ($request->filled('company_id') && $request->string('company_id')->toString() !== 'all') {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('entry_date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->where('entry_date', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($inner) use ($term): void {
                $inner->where('entry_no', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%')
                    ->orWhere('source_module', 'like', '%'.$term.'%')
                    ->orWhere('source_reference', 'like', '%'.$term.'%');
            });
        }
    }

    private function posChannelCorrectionAccounts(): array
    {
        $coa = app(CoaSettingService::class);
        try {
            $expenseAccount = $coa->resolveAccountByKey('pos_sale_sales_channel_admin_expense', '5016');
            $payableAccount = $coa->resolveAccountByKey('pos_sale_sales_channel_admin_payable', '2090');
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'account' => 'Akun default POS admin channel belum tersedia. Lengkapi Pengaturan COA terlebih dahulu.',
            ]);
        }

        if ((int) $expenseAccount->id === (int) $payableAccount->id) {
            throw ValidationException::withMessages([
                'account' => 'Akun beban admin channel dan hutang estimasi channel masih sama. Ubah Pengaturan COA terlebih dahulu.',
            ]);
        }

        return [$expenseAccount, $payableAccount];
    }

    private function accountLabel(Account $account): string
    {
        return $account->code.' - '.$account->name;
    }

    private function cashAccountReassignmentPreview(Request $request): ?array
    {
        if (! $request->filled('reassign_from')) {
            return null;
        }

        $fromAccountId = $request->integer('reassign_from');
        $accounts = Account::cashBankOptions();
        if (! $accounts->contains('id', $fromAccountId)) {
            return null;
        }

        $fromAccount = $accounts->firstWhere('id', $fromAccountId);
        $preview = app(CashAccountReassignmentService::class)->preview(
            $fromAccountId,
            $request->filled('date_from') ? $request->string('date_from')->toString() : null,
            $request->filled('date_to') ? $request->string('date_to')->toString() : null,
        );

        return [
            'from_account_id' => $fromAccountId,
            'from_account_label' => $fromAccount?->displayLabel(),
            ...$preview,
        ];
    }

    private function inventoryReservationSyncSummary(): array
    {
        $summary = app(ProjectMaterialReservationService::class)->summarizeAllWarehouseReservations();

        return [
            ...$summary,
            'can_run' => true,
        ];
    }

    private function inventoryStockRebuildSummary(): array
    {
        $summary = app(WarehouseStockRebuildService::class)->summarizeFromMovements();

        return [
            ...$summary,
            'can_run' => true,
        ];
    }

    private function supplierPaymentCompanySyncSummary(Request $request): array
    {
        $companyId = $request->filled('company_id') && $request->string('company_id')->toString() !== 'all'
            ? $request->integer('company_id')
            : null;

        return app(SupplierPaymentCompanySyncService::class)->summary(
            $companyId ? (int) $companyId : null,
            $request->filled('date_from') ? $request->string('date_from')->toString() : null,
            $request->filled('date_to') ? $request->string('date_to')->toString() : null,
        );
    }

    private function poExpenseReclassifySummary(): array
    {
        $inventoryAccount = Account::query()->where('code', '1201')->first();
        $expenseMapping = CategoryCoaMapping::query()
            ->where('domain', 'purchase_order')
            ->where('category', 'expense')
            ->with('account:id,code,name')
            ->first();

        $candidates = PurchaseOrder::query()
            ->whereIn('po_category', ['inventory', null])
            ->whereHas('receipts', fn ($q) => $q->where('status', DocumentStatus::Posted->value))
            ->orderByDesc('order_date')
            ->limit(50)
            ->get(['id', 'number', 'total_amount', 'order_date', 'po_category'])
            ->map(fn (PurchaseOrder $po) => [
                'number' => $po->number,
                'amount' => (float) $po->total_amount,
                'order_date' => $po->order_date?->toDateString(),
            ])
            ->values();

        return [
            'can_reclassify' => $inventoryAccount !== null && $expenseMapping?->account_id !== null,
            'expense_account_label' => $expenseMapping?->account?->code.' - '.$expenseMapping?->account?->name,
            'inventory_account_label' => $inventoryAccount?->code.' - '.$inventoryAccount?->name,
            'candidate_count' => $candidates->count(),
            'candidates' => $candidates,
            'message' => $expenseMapping?->account_id === null
                ? 'Mapping akun expense untuk purchase_order belum dikonfigurasi. Buka CoA Settings untuk mengatur.'
                : null,
        ];
    }

    public function reclassifyPoExpense(Request $request, GlPostingService $glPostingService): RedirectResponse
    {
        $validated = $request->validate([
            'po_numbers' => ['required', 'array', 'min:1', 'max:50'],
            'po_numbers.*' => ['required', 'string', 'exists:purchase_orders,number'],
        ]);

        $inventoryAccount = Account::query()->where('code', '1201')->firstOrFail();
        $expenseMapping = CategoryCoaMapping::query()
            ->where('domain', 'purchase_order')
            ->where('category', 'expense')
            ->value('account_id');

        if (! $expenseMapping) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Mapping akun expense untuk purchase_order belum dikonfigurasi. Buka CoA Settings untuk mengatur.']);
        }

        $poNumbers = collect($validated['po_numbers']);
        $succeeded = [];
        $skipped = [];

        foreach ($poNumbers as $poNumber) {
            $po = PurchaseOrder::query()
                ->with('receipts')
                ->where('number', $poNumber)
                ->first();

            if (! $po || $po->isExpense()) {
                $skipped[] = "{$poNumber} (sudah expense)";

                continue;
            }

            $postedReceipt = $po->receipts
                ->firstWhere('status', DocumentStatus::Posted->value);

            if (! $postedReceipt) {
                $skipped[] = "{$poNumber} (belum ada GRN terposting)";

                continue;
            }

            $amount = (float) $po->total_amount;
            $entryDate = $postedReceipt->received_date?->toDateString() ?? now()->toDateString();

            try {
                DB::transaction(function () use ($po, $glPostingService, $expenseMapping, $inventoryAccount, $amount, $entryDate): void {
                    $glPostingService->post(
                        ErpCompanyResolver::resolveForGlPosting(request()),
                        sourceModule: 'purchasing_reclassify',
                        sourceReference: $po->number,
                        description: 'Reclassifikasi biaya PO '.$po->number.' dari inventory ke expense',
                        entryDate: $entryDate,
                        lines: [
                            ['account_id' => (int) $expenseMapping, 'debit' => $amount, 'credit' => 0],
                            ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $amount],
                        ]
                    );

                    $po->update(['po_category' => 'expense']);
                });
                $succeeded[] = $po->number;
            } catch (\Throwable $e) {
                $skipped[] = "{$po->number} (error: {$e->getMessage()})";
            }
        }

        $parts = [];
        if ($succeeded !== []) {
            $totalAmount = PurchaseOrder::query()->whereIn('number', $succeeded)->sum('total_amount');
            $parts[] = count($succeeded).' PO berhasil direklasifikasi (total Rp '.number_format((float) $totalAmount, 0, ',', '.').')';
        }
        if ($skipped !== []) {
            $parts[] = count($skipped).' dilewati: '.implode('; ', array_slice($skipped, 0, 5));
        }

        return back()->with('flash', [
            'type' => $succeeded !== [] ? 'success' : 'warning',
            'message' => implode('. ', $parts),
        ]);
    }

    public function backfillUnitCosts(): RedirectResponse
    {
        $result = app(CogsBackfillService::class)->backfillUnitCosts();

        return back()->with('flash', [
            'type' => $result['products_updated'] > 0 ? 'success' : 'info',
            'message' => $result['products_updated'] > 0
                ? "Unit cost diperbarui: {$result['products_updated']} dari {$result['products_checked']} produk."
                : 'Tidak ada produk yang perlu diperbarui. Semua unit cost sudah terisi.',
        ]);
    }

    public function backfillCogs(): RedirectResponse
    {
        $result = app(CogsBackfillService::class)->backfillCogs();

        $parts = [];
        if ($result['succeeded'] > 0) {
            $parts[] = "{$result['succeeded']} COGS berhasil dibuat (total Rp ".number_format($result['total_cogs_amount'], 0, ',', '.').')';
        }
        if ($result['skipped'] > 0) {
            $parts[] = "{$result['skipped']} dilewati";
        }
        if ($result['errors'] !== []) {
            $parts[] = 'Error: '.implode('; ', array_slice($result['errors'], 0, 3));
        }

        return back()->with('flash', [
            'type' => $result['succeeded'] > 0 ? 'success' : 'warning',
            'message' => $parts !== [] ? implode('. ', $parts) : 'Tidak ada POS sale yang perlu diperbaiki.',
        ]);
    }

    public function estimateMaterialUnitCosts(): RedirectResponse
    {
        $result = app(ProjectMaterialBackfillService::class)->estimateUnitCosts();

        return back()->with('flash', [
            'type' => $result['products_updated'] > 0 ? 'success' : 'info',
            'message' => $result['products_updated'] > 0
                ? "Unit cost material diperbarui: {$result['products_updated']} dari {$result['products_checked']} produk."
                : 'Tidak ada produk material yang perlu diperbarui.',
        ]);
    }

    public function backfillMaterialCogs(): RedirectResponse
    {
        $result = app(ProjectMaterialBackfillService::class)->backfill();

        $parts = [];
        if ($result['succeeded'] > 0) {
            $parts[] = "{$result['succeeded']} proyek berhasil (total Rp ".number_format($result['total_cost'], 0, ',', '.').')';
        }
        if ($result['skipped'] > 0) {
            $parts[] = "{$result['skipped']} dilewati";
        }
        if ($result['errors'] !== []) {
            $parts[] = 'Error: '.implode('; ', array_slice($result['errors'], 0, 3));
        }

        return back()->with('flash', [
            'type' => $result['succeeded'] > 0 ? 'success' : 'warning',
            'message' => $parts !== [] ? implode('. ', $parts) : 'Tidak ada material proyek yang perlu diperbaiki.',
        ]);
    }
}
