<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Core\Services\FiscalPeriodService;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CashCategory;
use App\Models\CashOut;
use App\Models\CategoryCoaMapping;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CashOutController extends Controller
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {}

    public function index(Request $request)
    {
        $query = CashOut::with('project', 'creator')
            ->when(
                $request->filled('project_id') && Str::isUuid($request->project_id),
                fn ($q) => $q->where('project_id', $request->project_id)
            )
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->date_from, fn ($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('date', '<=', $request->date_to));

        $total = (float) (clone $query)->sum('amount');

        $cashOuts = $query->latest('date')->paginate($this->resolvedPerPage($request))->withQueryString()
            ->through(fn ($c) => [
                'id' => $c->id,
                'project_id' => $c->project_id,
                'cash_account_id' => $c->cash_account_id,
                'project_name' => $c->project?->name ?? 'Operasional Umum',
                'category' => $c->category,
                'amount' => (float) $c->amount,
                'date' => $c->date->format('Y-m-d'),
                'note' => $c->note,
                'recipient_name' => $c->recipient_name,
                'creator_name' => $c->creator->name,
                'document_status' => $c->document_status,
                'journal_entry_id' => $c->journal_entry_id,
            ]);

        $projects = Project::orderBy('name')->get(['id', 'name']);

        return Inertia::render('CashOut/Index', [
            'cashOuts' => $cashOuts,
            'total' => $total,
            'projects' => $projects,
            'cashAccounts' => Account::cashBankOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'filters' => $this->filtersWithPerPage($request, ['project_id', 'category', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'recipient_name' => 'nullable|string|max:255',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['document_status'] = DocumentStatus::Posted->value;
        $validated['approved_at'] = now();
        $validated['approved_by'] = Auth::id();
        $validated['posted_at'] = now();
        $validated['posted_by'] = Auth::id();
        $this->assertCategoryExists($validated['category']);
        $expenseAccountId = $this->resolveMappedAccountId($validated['category']);
        $companyId = ErpCompanyResolver::resolveForGlPosting($request);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Posting kas keluar');

        DB::transaction(function () use ($validated, $expenseAccountId, $companyId): void {
            $cashOut = CashOut::create($validated);
            $expenseAccount = Account::query()->findOrFail($expenseAccountId);
            $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);

            $entry = $this->glPostingService->post(
                $companyId,
                sourceModule: 'cash_out',
                sourceReference: (string) $cashOut->id,
                description: 'Kas keluar proyek '.$cashOut->project_id,
                entryDate: $validated['date'],
                lines: [
                    ['account_id' => $expenseAccount->id, 'debit' => $validated['amount'], 'credit' => 0],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $validated['amount']],
                ]
            );

            $cashOut->update(['journal_entry_id' => $entry->id]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil ditambahkan.']);
    }

    public function update(Request $request, CashOut $cashOut)
    {
        $companyId = $this->resolvedCompanyId($cashOut);
        $this->fiscalPeriodService->ensureDateIsOpen($cashOut->date ?? now(), $companyId, 'date', 'Perubahan kas keluar');
        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'recipient_name' => 'nullable|string|max:255',
        ]);

        $this->assertCategoryExists($validated['category']);
        $expenseAccountId = $this->resolveMappedAccountId($validated['category']);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Perubahan kas keluar');

        DB::transaction(function () use ($cashOut, $validated, $expenseAccountId, $companyId): void {
            // Pola reverse-and-repost: batalkan jurnal lama, buat jurnal baru dengan nilai updated
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
                description: 'Koreksi kas keluar proyek '.$cashOut->project_id,
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

    public function destroy(CashOut $cashOut)
    {
        $companyId = $this->resolvedCompanyId($cashOut);
        $this->fiscalPeriodService->ensureDateIsOpen($cashOut->date ?? now(), $companyId, 'date', 'Penghapusan kas keluar');

        DB::transaction(function () use ($cashOut): void {
            // Reverse journal entry sebelum delete agar GL tetap balance
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

    private function assertCategoryExists(string $category): void
    {
        if (CashCategory::isRetired('cash_out', $category)) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kas keluar ini sudah tidak digunakan. Gunakan kategori expense project yang aktif.',
            ]);
        }

        $exists = CashCategory::query()
            ->where('domain', 'cash_out')
            ->where('key', $category)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kas keluar tidak valid atau nonaktif.',
            ]);
        }
    }

    private function resolveMappedAccountId(string $category): int
    {
        $accountId = CategoryCoaMapping::query()
            ->where('domain', 'cash_out')
            ->where('category', $category)
            ->value('account_id');

        if (! $accountId) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kas keluar belum di-mapping ke akun CoA.',
            ]);
        }

        return (int) $accountId;
    }

    private function categoryOptions()
    {
        return CashCategory::query()
            ->where('domain', 'cash_out')
            ->whereNotIn('key', CashCategory::retiredKeysFor('cash_out'))
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

    private function resolvedCompanyId(CashOut $cashOut): ?int
    {
        $cashOut->loadMissing(['journalEntry:id,company_id', 'creator:id,company_id']);

        return $cashOut->journalEntry?->company_id ?? $cashOut->creator?->company_id;
    }
}
