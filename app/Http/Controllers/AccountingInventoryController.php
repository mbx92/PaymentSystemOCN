<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Core\Services\FiscalPeriodService;
use App\ERP\Shared\Enums\DocumentStatus;
use App\ERP\Shared\Services\DocumentNumberService;
use App\Models\AccountingInventoryRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountingInventoryController extends Controller
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = ErpCompanyResolver::resolveForReporting($request);

        $query = AccountingInventoryRecord::query()
            ->with([
                'assetAccount:id,code,name',
                'cashAccount:id,code,name',
                'creator:id,name',
                'journalEntry:id,entry_no,company_id',
                'journalEntry.company:id,name',
            ])
            ->when($companyId, fn ($q) => $q->whereHas(
                'journalEntry',
                fn ($jq) => $jq->where('company_id', $companyId)
            ))
            ->latest('acquisition_date')
            ->latest('id');

        if ($request->filled('date_from')) {
            $query->where('acquisition_date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->where('acquisition_date', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('asset_account_id')) {
            $query->where('asset_account_id', $request->integer('asset_account_id'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($term): void {
                $q->where('item_name', 'like', $term)
                    ->orWhere('note', 'like', $term)
                    ->orWhereHas('assetAccount', fn ($aq) => $aq
                        ->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term))
                    ->orWhereHas('cashAccount', fn ($aq) => $aq
                        ->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term))
                    ->orWhereHas('journalEntry', fn ($jq) => $jq->where('entry_no', 'like', $term))
                    ->orWhereHas('creator', fn ($cq) => $cq->where('name', 'like', $term));
            });
        }

        $total = (float) (clone $query)->sum('amount');

        $records = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (AccountingInventoryRecord $row) => [
                'id' => $row->id,
                'item_name' => $row->item_name,
                'qty' => (float) $row->qty,
                'amount' => (float) $row->amount,
                'acquisition_date' => $row->acquisition_date?->format('Y-m-d'),
                'asset_account_id' => $row->asset_account_id,
                'asset_account_label' => $row->assetAccount?->displayLabel(),
                'asset_account_code' => $row->assetAccount?->code,
                'cash_account_id' => $row->cash_account_id,
                'cash_account_label' => $row->cashAccount?->displayLabel(),
                'cash_account_code' => $row->cashAccount?->code,
                'note' => $row->note,
                'journal_entry_no' => $row->journalEntry?->entry_no,
                'company_name' => $row->journalEntry?->company?->name ?? 'Belum ditentukan',
                'creator_name' => $row->creator?->name,
            ]);

        $defaultAsset = Account::defaultInventoryAssetAccount();

        return Inertia::render('ERP/Accounting/Inventaris', [
            'records' => $records,
            'total' => $total,
            'assetAccounts' => Account::inventoryAssetOptions(),
            'cashAccounts' => Account::cashBankOptions(),
            'defaultAssetAccountId' => $defaultAsset?->id,
            'filters' => $this->filtersWithPerPage($request, [
                'company_id',
                'date_from',
                'date_to',
                'asset_account_id',
                'q',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'qty' => ['nullable', 'numeric', 'min:0.01'],
            'amount' => ['required', 'numeric', 'min:1'],
            'acquisition_date' => ['required', 'date'],
            'asset_account_id' => Account::inventoryAssetIdValidationRules(),
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $assetAccount = Account::query()->findOrFail((int) $validated['asset_account_id']);
        $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);
        $amount = (float) $validated['amount'];
        $qty = (float) ($validated['qty'] ?? 1);
        $itemName = trim($validated['item_name']);
        $note = trim((string) ($validated['note'] ?? ''));

        DB::transaction(function () use ($request, $validated, $assetAccount, $cashAccount, $amount, $qty, $itemName, $note): void {
            $record = AccountingInventoryRecord::query()->create([
                'item_name' => $itemName,
                'qty' => $qty,
                'amount' => $amount,
                'acquisition_date' => $validated['acquisition_date'],
                'asset_account_id' => $assetAccount->id,
                'cash_account_id' => $cashAccount->id,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);

            $entry = $this->glPostingService->post(
                ErpCompanyResolver::resolveForGlPosting($request),
                sourceModule: 'accounting_inventory',
                sourceReference: (string) $record->id,
                description: $this->buildDescription($itemName, $note),
                entryDate: $validated['acquisition_date'],
                lines: [
                    ['account_id' => $assetAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount],
                ],
            );

            $record->update(['journal_entry_id' => $entry->id]);
        });

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Pencatatan inventaris berhasil diposting ke jurnal.',
        ]);
    }

    public function update(Request $request, AccountingInventoryRecord $record): RedirectResponse
    {
        $this->authorizeMutation($record->created_by);
        $companyId = $this->resolvedCompanyId($record);
        $this->fiscalPeriodService->ensureDateIsOpen(
            $record->acquisition_date ?? now(),
            $companyId,
            'acquisition_date',
            'Perubahan inventaris'
        );

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'qty' => ['nullable', 'numeric', 'min:0.01'],
            'amount' => ['required', 'numeric', 'min:1'],
            'acquisition_date' => ['required', 'date'],
            'asset_account_id' => Account::inventoryAssetIdValidationRules(),
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->fiscalPeriodService->ensureDateIsOpen(
            $validated['acquisition_date'],
            $companyId,
            'acquisition_date',
            'Perubahan inventaris'
        );

        $assetAccount = Account::query()->findOrFail((int) $validated['asset_account_id']);
        $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);
        $amount = (float) $validated['amount'];
        $qty = (float) ($validated['qty'] ?? 1);
        $itemName = trim($validated['item_name']);
        $note = trim((string) ($validated['note'] ?? ''));

        DB::transaction(function () use ($record, $validated, $assetAccount, $cashAccount, $amount, $qty, $itemName, $note, $companyId): void {
            if ($record->journal_entry_id) {
                $this->createReversalJournalEntry($record->journal_entry_id, $companyId);
            }

            $record->update([
                'item_name' => $itemName,
                'qty' => $qty,
                'amount' => $amount,
                'acquisition_date' => $validated['acquisition_date'],
                'asset_account_id' => $assetAccount->id,
                'cash_account_id' => $cashAccount->id,
                'note' => $note !== '' ? $note : null,
            ]);

            $entry = $this->glPostingService->post(
                $companyId,
                sourceModule: 'accounting_inventory',
                sourceReference: (string) $record->id,
                description: 'Koreksi '.$this->buildDescription($itemName, $note),
                entryDate: $validated['acquisition_date'],
                lines: [
                    ['account_id' => $assetAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount],
                ],
            );

            $record->update(['journal_entry_id' => $entry->id]);
        });

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Pencatatan inventaris berhasil diperbarui dan jurnal GL telah disinkronkan.',
        ]);
    }

    public function destroy(AccountingInventoryRecord $record): RedirectResponse
    {
        $this->authorizeMutation($record->created_by);
        $companyId = $this->resolvedCompanyId($record);
        $this->fiscalPeriodService->ensureDateIsOpen(
            $record->acquisition_date ?? now(),
            $companyId,
            'acquisition_date',
            'Penghapusan inventaris'
        );

        DB::transaction(function () use ($record, $companyId): void {
            if ($record->journal_entry_id) {
                $this->createReversalJournalEntry($record->journal_entry_id, $companyId);
            }
            $record->delete();
        });

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Pencatatan inventaris berhasil dihapus dan jurnal GL telah di-reverse.',
        ]);
    }

    private function buildDescription(string $itemName, string $note): string
    {
        $description = "Pembelian inventaris: {$itemName}";
        if ($note !== '') {
            $description .= " — {$note}";
        }

        return $description;
    }

    private function createReversalJournalEntry(int $originalEntryId, ?int $companyId = null): void
    {
        $original = JournalEntry::query()->with('lines')->find($originalEntryId);

        if (! $original) {
            return;
        }

        $entryNo = app(DocumentNumberService::class)->next('accounting', 'journal_entry_reversal', [
            'prefix' => 'REV',
            'padding_length' => 6,
        ]);

        $reversal = JournalEntry::query()->create([
            'company_id' => $companyId ?? $original->company_id,
            'entry_no' => $entryNo,
            'entry_date' => now()->toDateString(),
            'description' => 'Reversal: '.$original->description,
            'status' => DocumentStatus::Posted,
            'source_module' => 'reversal',
            'source_reference' => $original->entry_no,
            'posted_at' => now(),
            'posted_by' => Auth::id(),
            'reversed_entry_id' => $original->id,
        ]);

        foreach ($original->lines as $line) {
            JournalLine::query()->create([
                'journal_entry_id' => $reversal->id,
                'account_id' => $line->account_id,
                'description' => $line->description,
                'debit' => $line->credit,
                'credit' => $line->debit,
            ]);
        }
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

    private function resolvedCompanyId(AccountingInventoryRecord $record): int
    {
        $record->loadMissing(['journalEntry:id,company_id', 'creator:id,company_id']);

        return (int) ($record->journalEntry?->company_id
            ?? $record->creator?->company_id
            ?? ErpCompanyResolver::resolveForGlPosting(request()));
    }
}
