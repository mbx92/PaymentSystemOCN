<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Core\Services\FiscalPeriodService;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CashCategory;
use App\Models\CashIn;
use App\Models\CategoryCoaMapping;
use App\Models\PaymentMethod;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CashInController extends Controller
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {}

    public function index(Request $request)
    {
        $query = CashIn::with('project', 'creator', 'paymentMethod')
            ->when(
                $request->filled('project_id') && Str::isUuid($request->project_id),
                fn ($q) => $q->where('project_id', $request->project_id)
            )
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->date_from, fn ($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('date', '<=', $request->date_to));

        $total = (float) (clone $query)->sum('amount');

        $cashIns = $query->latest('date')->paginate($this->resolvedPerPage($request))->withQueryString()
            ->through(fn ($c) => [
                'id' => $c->id,
                'project_id' => $c->project_id,
                'project_name' => $c->project?->name ?? 'Manual / Umum',
                'payment_method_id' => $c->payment_method_id,
                'payment_method_name' => $c->paymentMethod?->name,
                'cash_account_id' => $c->cash_account_id,
                'category' => $c->category,
                'amount' => (float) $c->amount,
                'date' => $c->date->format('Y-m-d'),
                'note' => $c->note,
                'creator_name' => $c->creator->name,
                'document_status' => $c->document_status,
                'journal_entry_id' => $c->journal_entry_id,
            ]);

        $projects = Project::orderBy('name')->get(['id', 'name']);

        return Inertia::render('CashIn/Index', [
            'cashIns' => $cashIns,
            'total' => $total,
            'projects' => $projects,
            'paymentMethods' => PaymentMethod::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'cashAccounts' => Account::cashBankOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'filters' => $this->filtersWithPerPage($request, ['project_id', 'category', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|uuid|exists:projects,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['document_status'] = DocumentStatus::Posted->value;
        $validated['approved_at'] = now();
        $validated['approved_by'] = Auth::id();
        $validated['posted_at'] = now();
        $validated['posted_by'] = Auth::id();
        $this->assertCategoryExists($validated['category']);
        $revenueAccountId = $this->resolveMappedAccountId($validated['category']);
        $companyId = ErpCompanyResolver::resolveForGlPosting($request);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Posting kas masuk');

        DB::transaction(function () use ($validated, $revenueAccountId, $companyId): void {
            $cashIn = CashIn::create($validated);

            $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);
            $revenueAccount = Account::query()->findOrFail($revenueAccountId);

            $entry = $this->glPostingService->post(
                $companyId,
                sourceModule: 'cash_in',
                sourceReference: (string) $cashIn->id,
                description: 'Kas masuk proyek '.$cashIn->project_id,
                entryDate: $validated['date'],
                lines: [
                    ['account_id' => $cashAccount->id, 'debit' => $validated['amount'], 'credit' => 0],
                    ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $validated['amount']],
                ]
            );

            $cashIn->update(['journal_entry_id' => $entry->id]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil ditambahkan.']);
    }

    public function update(Request $request, CashIn $cashIn)
    {
        $companyId = $this->resolvedCompanyId($cashIn);
        $this->fiscalPeriodService->ensureDateIsOpen($cashIn->date ?? now(), $companyId, 'date', 'Perubahan kas masuk');
        $validated = $request->validate([
            'project_id' => 'required|uuid|exists:projects,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        $this->assertCategoryExists($validated['category']);
        $revenueAccountId = $this->resolveMappedAccountId($validated['category']);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Perubahan kas masuk');

        DB::transaction(function () use ($cashIn, $validated, $revenueAccountId, $companyId): void {
            // Pola reverse-and-repost: batalkan jurnal lama, buat jurnal baru dengan nilai updated
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
                description: 'Koreksi kas masuk proyek '.$cashIn->project_id,
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

    public function destroy(CashIn $cashIn)
    {
        $companyId = $this->resolvedCompanyId($cashIn);
        $this->fiscalPeriodService->ensureDateIsOpen($cashIn->date ?? now(), $companyId, 'date', 'Penghapusan kas masuk');

        DB::transaction(function () use ($cashIn): void {
            // Reverse journal entry sebelum delete agar GL tetap balance
            if ($cashIn->journal_entry_id) {
                $this->reverseJournalEntryLines($cashIn->journal_entry_id);
            }

            $cashIn->delete();
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil dihapus dan jurnal GL telah di-reverse.']);
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

    private function assertCategoryExists(string $category): void
    {
        if (CashCategory::isRetired('cash_in', $category)) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kas masuk ini sudah tidak digunakan. Pemasukan project dicatat dari termin/invoice.',
            ]);
        }

        $exists = CashCategory::query()
            ->where('domain', 'cash_in')
            ->where('key', $category)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kas masuk tidak valid atau nonaktif.',
            ]);
        }
    }

    private function resolveMappedAccountId(string $category): int
    {
        $accountId = CategoryCoaMapping::query()
            ->where('domain', 'cash_in')
            ->where('category', $category)
            ->value('account_id');

        if (! $accountId) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kas masuk belum di-mapping ke akun CoA.',
            ]);
        }

        return (int) $accountId;
    }

    private function categoryOptions()
    {
        return CashCategory::query()
            ->where('domain', 'cash_in')
            ->whereNotIn('key', CashCategory::retiredKeysFor('cash_in'))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['key', 'label'])
            ->map(fn (CashCategory $category): array => [
                'value' => $category->key,
                'label' => $category->label,
            ])
            ->values();
    }

    private function resolvedCompanyId(CashIn $cashIn): ?int
    {
        $cashIn->loadMissing(['journalEntry:id,company_id', 'creator:id,company_id']);

        return $cashIn->journalEntry?->company_id ?? $cashIn->creator?->company_id;
    }
}
