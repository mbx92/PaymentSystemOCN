<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Core\Services\FiscalPeriodService;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CashOut;
use App\Models\CategoryCoaMapping;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperationalController extends Controller
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = ErpCompanyResolver::resolveForReporting($request);

        $query = CashOut::query()
            ->with(['project:id,name', 'creator:id,name'])
            ->where('category', 'operasional')
            ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn ($jq) => $jq->where('company_id', $companyId)))
            ->when(
                $request->filled('project_id') && Str::isUuid($request->string('project_id')->toString()),
                fn ($q) => $q->where('project_id', $request->string('project_id')->toString())
            )
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('date', '<=', $request->date('date_to')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where('note', 'like', $term)
                    ->orWhere('recipient_name', 'like', $term)
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', $term));
            });

        $total = (float) (clone $query)->sum('amount');

        $rows = $query
            ->latest('date')
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (CashOut $c) => [
                'id' => $c->id,
                'date' => $c->date?->format('Y-m-d'),
                'project_id' => $c->project_id,
                'cash_account_id' => $c->cash_account_id,
                'project_name' => $c->project?->name ?? 'Operasional Umum',
                'amount' => (float) $c->amount,
                'recipient_name' => $c->recipient_name,
                'note' => $c->note,
                'creator_name' => $c->creator?->name ?? '-',
                'document_status' => $c->document_status,
                'journal_entry_id' => $c->journal_entry_id,
            ]);

        return Inertia::render('ERP/Accounting/Operational', [
            'rows' => $rows,
            'total' => $total,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'cashAccounts' => Account::cashBankOptions(),
            'filters' => $this->filtersWithPerPage($request, ['project_id', 'company_id', 'date_from', 'date_to', 'q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'recipient_name' => 'nullable|string|max:255',
        ]);
        $companyId = ErpCompanyResolver::resolveForGlPosting($request);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Posting biaya operasional');

        DB::transaction(function () use ($validated, $companyId): void {
            $expenseAccountId = $this->resolveExpenseAccountId();
            $payload = [
                'project_id' => $validated['project_id'] ?? null,
                'cash_account_id' => $validated['cash_account_id'],
                'category' => 'operasional',
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
                sourceModule: 'operational_cash_out',
                sourceReference: (string) $cashOut->id,
                description: 'Biaya operasional'.($cashOut->project_id ? ' proyek '.$cashOut->project_id : ''),
                entryDate: $payload['date'],
                lines: [
                    ['account_id' => $expenseAccount->id, 'debit' => $payload['amount'], 'credit' => 0],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $payload['amount']],
                ]
            );

            $cashOut->update(['journal_entry_id' => $entry->id]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Biaya operasional berhasil ditambahkan.']);
    }

    public function update(Request $request, CashOut $cashOut): RedirectResponse
    {
        abort_unless($cashOut->category === 'operasional', 404);
        $this->authorizeMutation($cashOut->created_by);
        $companyId = $this->resolvedCompanyId($cashOut);
        $this->fiscalPeriodService->ensureDateIsOpen($cashOut->date ?? now(), $companyId, 'date', 'Perubahan biaya operasional');

        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'recipient_name' => 'nullable|string|max:255',
        ]);
        $this->fiscalPeriodService->ensureDateIsOpen($validated['date'], $companyId, 'date', 'Perubahan biaya operasional');

        $cashOut->update(array_merge($validated, ['category' => 'operasional']));

        return back()->with('flash', ['type' => 'success', 'message' => 'Biaya operasional berhasil diperbarui.']);
    }

    public function destroy(CashOut $cashOut): RedirectResponse
    {
        abort_unless($cashOut->category === 'operasional', 404);
        $this->authorizeMutation($cashOut->created_by);
        $this->fiscalPeriodService->ensureDateIsOpen($cashOut->date ?? now(), $this->resolvedCompanyId($cashOut), 'date', 'Penghapusan biaya operasional');
        $cashOut->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Biaya operasional berhasil dihapus.']);
    }

    private function resolveExpenseAccountId(): int
    {
        $accountId = CategoryCoaMapping::query()
            ->where('domain', 'cash_out')
            ->where('category', 'operasional')
            ->value('account_id');

        if (! $accountId) {
            throw ValidationException::withMessages([
                'category' => 'Kategori operasional belum di-mapping ke akun CoA.',
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

    private function resolvedCompanyId(CashOut $cashOut): ?int
    {
        $cashOut->loadMissing(['journalEntry:id,company_id', 'creator:id,company_id']);

        return $cashOut->journalEntry?->company_id ?? $cashOut->creator?->company_id;
    }
}
