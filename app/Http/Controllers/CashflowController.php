<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Services\CoaSettingService;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CategoryCoaMapping;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\CashCategory;
use App\Models\PaymentMethod;
use App\Models\PosSale;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CashflowController extends Controller
{
    public function __construct(private readonly GlPostingService $glPostingService) {}

    public function index(Request $request): Response
    {
        $entries = $this->buildEntries($request);
        $cashInTotal = (float) $entries->where('type', 'in')->sum('amount');
        $cashOutTotal = (float) $entries->where('type', 'out')->sum('amount');

        return Inertia::render('ERP/Accounting/Cashflow', [
            'entries' => $entries->values(),
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
            'cashAccounts' => Account::query()
                ->where('is_active', true)
                ->where('type', 'asset')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'filters' => $request->only(['type', 'project_id', 'category', 'date_from', 'date_to', 'q']),
            'categoryOptions' => [
                'in' => CashCategory::query()
                    ->where('domain', 'cash_in')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get(['key', 'label'])
                    ->map(fn ($c) => ['value' => $c->key, 'label' => $c->label])
                    ->values(),
                'out' => CashCategory::query()
                    ->where('domain', 'cash_out')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get(['key', 'label'])
                    ->map(fn ($c) => ['value' => $c->key, 'label' => $c->label])
                    ->values(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out',
            'project_id' => 'nullable|uuid|exists:projects,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_account_id' => 'required|exists:accounts,id',
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'recipient_name' => 'nullable|string|max:255',
        ]);

        if ($validated['type'] === 'in') {
            $this->storeCashInEntry($validated);
            return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil ditambahkan.']);
        }

        $this->storeCashOutEntry($validated);
        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil ditambahkan.']);
    }

    public function updateCashIn(Request $request, CashIn $cashIn): RedirectResponse
    {
        $this->authorizeMutation($cashIn->created_by);
        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_account_id' => 'required|exists:accounts,id',
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);
        $this->assertCategoryExists('cash_in', $validated['category']);
        $this->resolveMappedAccountId('cash_in', $validated['category']);
        $cashIn->update($validated);
        return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil diperbarui.']);
    }

    public function updateCashOut(Request $request, CashOut $cashOut): RedirectResponse
    {
        $this->authorizeMutation($cashOut->created_by);
        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'cash_account_id' => 'required|exists:accounts,id',
            'category' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'recipient_name' => 'nullable|string|max:255',
        ]);
        $this->assertCategoryExists('cash_out', $validated['category']);
        $this->resolveMappedAccountId('cash_out', $validated['category']);
        $cashOut->update($validated);
        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil diperbarui.']);
    }

    public function destroyCashIn(CashIn $cashIn): RedirectResponse
    {
        $this->authorizeMutation($cashIn->created_by);
        $cashIn->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Kas masuk berhasil dihapus.']);
    }

    public function destroyCashOut(CashOut $cashOut): RedirectResponse
    {
        $this->authorizeMutation($cashOut->created_by);
        $cashOut->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil dihapus.']);
    }

    private function buildEntries(Request $request): Collection
    {
        $cashIns = CashIn::query()
            ->with(['project:id,name', 'creator:id,name', 'paymentMethod:id,name', 'cashAccount:id,code,name'])
            ->when($request->filled('project_id') && Str::isUuid($request->string('project_id')->toString()), fn ($q) => $q->where('project_id', $request->string('project_id')->toString()))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('date', '<=', $request->date('date_to')))
            ->get()
            ->map(fn (CashIn $entry) => [
                'id' => $entry->id,
                'type' => 'in',
                'source_name' => $entry->project_payment_id
                    ? 'Termin Project'
                    : ($entry->category === 'pendapatan_project' ? 'Invoice Project' : 'Kas Masuk'),
                'reference_no' => $entry->project_payment_id ? 'TERM-'.$entry->project_payment_id : (string) $entry->id,
                'date' => $entry->date?->format('Y-m-d'),
                'project_name' => $entry->project?->name ?? '-',
                'project_id' => $entry->project_id,
                'category' => $entry->category,
                'amount' => (float) $entry->amount,
                'payment_method_name' => $entry->paymentMethod?->name,
                'payment_method_id' => $entry->payment_method_id,
                'cash_account_id' => $entry->cash_account_id,
                'cash_account_name' => $entry->cashAccount ? ($entry->cashAccount->code.' - '.$entry->cashAccount->name) : '-',
                'recipient_name' => null,
                'note' => $entry->note,
                'creator_name' => $entry->creator?->name ?? '-',
                'document_status' => $entry->document_status,
                'journal_entry_id' => $entry->journal_entry_id,
                'created_at' => optional($entry->created_at)->timestamp ?? 0,
            ]);

        $cashOuts = CashOut::query()
            ->with(['project:id,name', 'creator:id,name', 'cashAccount:id,code,name'])
            ->when($request->filled('project_id') && Str::isUuid($request->string('project_id')->toString()), fn ($q) => $q->where('project_id', $request->string('project_id')->toString()))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('date', '<=', $request->date('date_to')))
            ->get()
            ->map(fn (CashOut $entry) => [
                'id' => $entry->id,
                'type' => 'out',
                'source_name' => 'Kas Keluar',
                'reference_no' => (string) $entry->id,
                'date' => $entry->date?->format('Y-m-d'),
                'project_name' => $entry->project?->name ?? '-',
                'project_id' => $entry->project_id,
                'category' => $entry->category,
                'amount' => (float) $entry->amount,
                'payment_method_name' => null,
                'payment_method_id' => null,
                'cash_account_id' => $entry->cash_account_id,
                'cash_account_name' => $entry->cashAccount ? ($entry->cashAccount->code.' - '.$entry->cashAccount->name) : '-',
                'recipient_name' => $entry->recipient_name,
                'note' => $entry->note,
                'creator_name' => $entry->creator?->name ?? '-',
                'document_status' => $entry->document_status,
                'journal_entry_id' => $entry->journal_entry_id,
                'created_at' => optional($entry->created_at)->timestamp ?? 0,
            ]);

        $posCashAccount = app(CoaSettingService::class)->resolveAccountByKey('pos_sale_cash_account', '1001');
        $posSales = $this->buildPosEntries($request, $posCashAccount);

        $merged = $cashIns->merge($cashOuts)
            ->merge($posSales)
            ->sortByDesc(fn (array $row) => sprintf('%s-%d', $row['date'] ?? '0000-00-00', $row['created_at']));

        if (! $request->filled('type') && ! $request->filled('q')) {
            return $merged->take(500)->values();
        }

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

        return $filtered->take(500)->values();
    }

    private function buildPosEntries(Request $request, Account $posCashAccount): Collection
    {
        if ($request->filled('project_id')) {
            return collect();
        }

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
            ->whereIn('source_reference', $sales->pluck('number')->all())
            ->get(['id', 'source_module', 'source_reference'])
            ->keyBy(fn (JournalEntry $entry) => $entry->source_module.'|'.$entry->source_reference);

        return $sales->map(function (PosSale $sale) use ($posCashAccount, $journalMap): array {
            $status = (string) $sale->status;
            $type = $status === 'refunded' ? 'out' : 'in';
            $category = $status === 'refunded' ? 'refund_penjualan_pos' : 'penjualan_pos';
            $sourceModule = match ($status) {
                'refunded' => 'pos_sale_refund',
                'reopened' => 'pos_sale_reopen',
                default => 'pos_sale',
            };

            return [
                'id' => 'pos-'.$sale->id,
                'type' => $type,
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
                'journal_entry_id' => $journalMap->get($sourceModule.'|'.$sale->number)?->id,
                'created_at' => optional($sale->sold_at)->timestamp ?? 0,
            ];
        });
    }

    private function storeCashInEntry(array $validated): void
    {
        $this->assertCategoryExists('cash_in', $validated['category']);
        $revenueAccountId = $this->resolveMappedAccountId('cash_in', $validated['category']);

        $payload = [
            'project_id' => $validated['project_id'],
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
    }

    private function storeCashOutEntry(array $validated): void
    {
        $this->assertCategoryExists('cash_out', $validated['category']);
        $expenseAccountId = $this->resolveMappedAccountId('cash_out', $validated['category']);

        $payload = [
            'project_id' => $validated['project_id'],
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
    }

    private function assertCategoryExists(string $domain, string $category): void
    {
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
}
