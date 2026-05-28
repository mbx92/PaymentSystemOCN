<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CashCategory;
use App\Models\CashOut;
use App\Models\ProcurementImportStaging;
use App\Models\CategoryCoaMapping;
use App\Models\TeamDistribution;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ERPAccountingPaymentController extends Controller
{
    public function __construct(private readonly GlPostingService $glPostingService) {}

    public function index(Request $request): Response
    {
        $searchOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $paidHistorySearch = trim((string) $request->string('paid_history_q'));
        $paidHistoryPerPage = $this->resolvedPaidSupplierBillHistoryPerPage($request);

        $payables = Payable::query()
            ->with(['vendor:id,code,name', 'purchaseOrder:id,number,notes', 'goodsReceipt:id,number', 'payments.cashAccount:id,code,name'])
            ->orderByRaw('(amount - paid_amount) desc')
            ->orderBy('due_date')
            ->get()
            ->map(function (Payable $payable): array {
                $amount = (float) $payable->amount;
                $paid = (float) $payable->paid_amount;
                $legacyProcurementDate = $this->resolvedLegacySupplierPaymentDate($payable);

                return [
                    'id' => $payable->id,
                    'bill_no' => $payable->bill_no,
                    'vendor_name' => $payable->vendor?->name,
                    'vendor_code' => $payable->vendor?->code,
                    'po_number' => $payable->purchaseOrder?->number,
                    'grn_number' => $payable->goodsReceipt?->number,
                    'bill_date' => $payable->bill_date?->toDateString(),
                    'due_date' => $payable->due_date?->toDateString(),
                    'amount' => $amount,
                    'paid_amount' => $paid,
                    'outstanding_amount' => max($amount - $paid, 0),
                    'status' => $payable->status->value,
                    'is_legacy_procurement' => $legacyProcurementDate !== null,
                    'legacy_payment_date' => $legacyProcurementDate,
                    'payments' => $payable->payments
                        ->sortByDesc('payment_date')
                        ->map(fn (PayablePayment $payment) => [
                            'id' => $payment->id,
                            'payment_date' => $payment->payment_date?->toDateString(),
                            'amount' => (float) $payment->amount,
                            'cash_account' => $payment->cashAccount
                                ? $payment->cashAccount->code.' - '.$payment->cashAccount->name
                                : '-',
                            'journal_entry_id' => $payment->journal_entry_id,
                            'note' => $payment->note,
                        ])
                        ->values(),
                ];
            });

        $paidPayables = Payable::query()
            ->with(['vendor:id,code,name', 'purchaseOrder:id,number,notes', 'goodsReceipt:id,number'])
            ->whereRaw('paid_amount >= amount')
            ->when($paidHistorySearch !== '', function ($query) use ($paidHistorySearch, $searchOperator): void {
                $query->where(function ($inner) use ($paidHistorySearch, $searchOperator): void {
                    $inner->where('bill_no', $searchOperator, '%'.$paidHistorySearch.'%')
                        ->orWhereHas('vendor', function ($vendorQuery) use ($paidHistorySearch, $searchOperator): void {
                            $vendorQuery->where('name', $searchOperator, '%'.$paidHistorySearch.'%')
                                ->orWhere('code', $searchOperator, '%'.$paidHistorySearch.'%');
                        })
                        ->orWhereHas('purchaseOrder', fn ($poQuery) => $poQuery->where('number', $searchOperator, '%'.$paidHistorySearch.'%'))
                        ->orWhereHas('goodsReceipt', fn ($grQuery) => $grQuery->where('number', $searchOperator, '%'.$paidHistorySearch.'%'));
                });
            })
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->paginate($paidHistoryPerPage)
            ->withQueryString()
            ->through(function (Payable $payable): array {
                $legacyProcurementDate = $this->resolvedLegacySupplierPaymentDate($payable);

                return [
                    'id' => $payable->id,
                    'bill_no' => $payable->bill_no,
                    'vendor_name' => $payable->vendor?->name,
                    'vendor_code' => $payable->vendor?->code,
                    'po_number' => $payable->purchaseOrder?->number,
                    'grn_number' => $payable->goodsReceipt?->number,
                    'bill_date' => $payable->bill_date?->toDateString(),
                    'due_date' => $payable->due_date?->toDateString(),
                    'amount' => (float) $payable->amount,
                    'paid_amount' => (float) $payable->paid_amount,
                    'outstanding_amount' => max((float) $payable->amount - (float) $payable->paid_amount, 0),
                    'status' => $payable->status->value,
                    'is_legacy_procurement' => $legacyProcurementDate !== null,
                    'legacy_payment_date' => $legacyProcurementDate,
                ];
            });

        return Inertia::render('ERP/Accounting/Payments', [
            'payables' => $payables,
            'paidPayables' => $paidPayables,
            'summary' => [
                'payables_total' => (float) $payables->sum('amount'),
                'paid_total' => (float) $payables->sum('paid_amount'),
                'outstanding_total' => (float) $payables->sum('outstanding_amount'),
                'open_count' => $payables->filter(fn (array $row) => $row['outstanding_amount'] > 0)->count(),
            ],
            'filters' => [
                'paid_history_q' => $paidHistorySearch,
                'paid_history_per_page' => $paidHistoryPerPage,
            ],
            'cashAccounts' => Account::cashBankOptions(),
        ]);
    }

    private function resolvedPaidSupplierBillHistoryPerPage(Request $request): int
    {
        $perPage = (int) $request->query('paid_history_per_page', 25);
        $allowed = [25, 50, 75, 100, 125, 150, 175, 200, 225, 250];

        return in_array($perPage, $allowed, true) ? $perPage : 25;
    }

    public function storeSupplierPayment(Request $request, Payable $payable): RedirectResponse
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'note' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($payable, $validated, $request): void {
            $lockedPayable = Payable::query()
                ->with([
                    'vendor',
                    'journalEntry:id,company_id',
                    'goodsReceipt.warehouse:id,company_id',
                ])
                ->lockForUpdate()
                ->findOrFail($payable->id);

            $outstanding = max((float) $lockedPayable->amount - (float) $lockedPayable->paid_amount, 0);
            $amount = (float) $validated['amount'];
            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran melebihi sisa hutang supplier.',
                ]);
            }

            $payableAccount = Account::query()->where('code', '2001')->firstOrFail();
            $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);
            $paymentDate = $this->resolvedSupplierPaymentDate($lockedPayable, (string) $validated['payment_date']);

            $entry = $this->glPostingService->post(
                $this->resolveSupplierPaymentCompanyId($lockedPayable, $request),
                sourceModule: 'supplier_payment',
                sourceReference: $lockedPayable->bill_no,
                description: 'Pembayaran supplier '.$lockedPayable->bill_no.' - '.($lockedPayable->vendor?->name ?? 'Supplier'),
                entryDate: $paymentDate,
                lines: [
                    ['account_id' => $payableAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount],
                ],
            );

            PayablePayment::query()->create([
                'payable_id' => $lockedPayable->id,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'cash_account_id' => (int) $validated['cash_account_id'],
                'note' => $validated['note'] ?? null,
                'journal_entry_id' => $entry->id,
                'paid_by' => Auth::id(),
            ]);

            $newPaidAmount = (float) $lockedPayable->paid_amount + $amount;
            $lockedPayable->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newPaidAmount >= (float) $lockedPayable->amount
                    ? DocumentStatus::Paid
                    : DocumentStatus::PartiallyPaid,
            ]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Pembayaran supplier berhasil diposting ke hutang usaha dan kas/bank.']);
    }

    private function resolveSupplierPaymentCompanyId(Payable $payable, Request $request): int
    {
        if ($payable->journalEntry?->company_id) {
            return (int) $payable->journalEntry->company_id;
        }

        if ($payable->goodsReceipt?->warehouse?->company_id) {
            return (int) $payable->goodsReceipt->warehouse->company_id;
        }

        return ErpCompanyResolver::resolveForGlPosting($request);
    }

    public function memberPayments(Request $request): Response
    {
        $members = User::role(['anggota', 'manajer'])->orderBy('name')->get(['id', 'name']);
        $searchOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $historySearch = trim((string) $request->string('history_q'));
        $historyPerPage = $this->resolvedMemberPaymentHistoryPerPage($request);

        $baseQuery = TeamDistribution::query()
            ->with(['project:id,name,status,import_key,finished_at', 'user:id,name', 'cashOut:id,date,journal_entry_id'])
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('year'), fn ($q) => $q->whereYear('created_at', $request->integer('year')))
            ->when($request->string('status')->toString() === 'unpaid', fn ($q) => $q->whereNull('paid_at'))
            ->when($request->string('status')->toString() === 'paid', fn ($q) => $q->whereNotNull('paid_at'));

        $distributions = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TeamDistribution $distribution) => [
                'id' => $distribution->id,
                'user_id' => $distribution->user_id,
                'user_name' => $distribution->user?->name ?? '-',
                'project_id' => $distribution->project_id,
                'project_name' => $distribution->project?->name ?? '-',
                'project_status' => $distribution->project?->status,
                'is_legacy_import' => filled($distribution->project?->import_key),
                'legacy_payment_date' => $distribution->project?->import_key
                    ? $distribution->project?->finished_at?->toDateString()
                    : null,
                'role_in_project' => $distribution->role_in_project,
                'percentage' => (float) $distribution->percentage,
                'base_pay' => (float) $distribution->base_pay,
                'bonus' => (float) $distribution->bonus,
                'total_pay' => (float) $distribution->total_pay,
                'is_paid' => $distribution->isPaid(),
                'paid_at' => $distribution->paid_at?->toDateString(),
                'payment_date' => $distribution->cashOut?->date?->toDateString(),
                'journal_entry_id' => $distribution->cashOut?->journal_entry_id,
            ]);

        $paidDistributions = (clone $baseQuery)
            ->whereNotNull('paid_at')
            ->when($historySearch !== '', function ($query) use ($historySearch, $searchOperator): void {
                $query->where(function ($inner) use ($historySearch, $searchOperator): void {
                    $inner->whereHas('user', fn ($userQuery) => $userQuery->where('name', $searchOperator, '%'.$historySearch.'%'))
                        ->orWhereHas('project', fn ($projectQuery) => $projectQuery->where('name', $searchOperator, '%'.$historySearch.'%'))
                        ->orWhere('role_in_project', $searchOperator, '%'.$historySearch.'%');
                });
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->paginate($historyPerPage)
            ->withQueryString()
            ->through(fn (TeamDistribution $distribution) => [
                'id' => $distribution->id,
                'user_id' => $distribution->user_id,
                'user_name' => $distribution->user?->name ?? '-',
                'project_id' => $distribution->project_id,
                'project_name' => $distribution->project?->name ?? '-',
                'project_status' => $distribution->project?->status,
                'is_legacy_import' => filled($distribution->project?->import_key),
                'legacy_payment_date' => $distribution->project?->import_key
                    ? $distribution->project?->finished_at?->toDateString()
                    : null,
                'role_in_project' => $distribution->role_in_project,
                'percentage' => (float) $distribution->percentage,
                'base_pay' => (float) $distribution->base_pay,
                'bonus' => (float) $distribution->bonus,
                'total_pay' => (float) $distribution->total_pay,
                'is_paid' => $distribution->isPaid(),
                'paid_at' => $distribution->paid_at?->toDateString(),
                'payment_date' => $distribution->cashOut?->date?->toDateString(),
                'journal_entry_id' => $distribution->cashOut?->journal_entry_id,
            ]);

        $unpaid = $distributions->where('is_paid', false);
        $paid = $distributions->where('is_paid', true);

        return Inertia::render('ERP/Accounting/MemberPayments', [
            'members' => $members,
            'distributions' => $distributions->values(),
            'paidDistributions' => $paidDistributions,
            'summary' => [
                'outstanding_total' => (float) $unpaid->sum('total_pay'),
                'paid_total' => (float) $paid->sum('total_pay'),
                'open_count' => $unpaid->count(),
            ],
            'filters' => array_merge(
                $request->only(['user_id', 'year', 'status', 'history_q']),
                ['history_per_page' => $historyPerPage]
            ),
            'years' => range(now()->year, now()->year - 4),
            'cashAccounts' => Account::cashBankOptions(),
        ]);
    }

    private function resolvedMemberPaymentHistoryPerPage(Request $request): int
    {
        $perPage = (int) $request->query('history_per_page', 25);
        $allowed = [25, 50, 75, 100, 125, 150, 175, 200, 225, 250];

        return in_array($perPage, $allowed, true) ? $perPage : 25;
    }

    public function storeMemberPayment(Request $request, TeamDistribution $teamDistribution): RedirectResponse
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'cash_account_id' => Account::cashBankIdValidationRules(),
            'note' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($teamDistribution, $validated, $request): void {
            $locked = TeamDistribution::query()
                ->with(['project', 'user'])
                ->lockForUpdate()
                ->findOrFail($teamDistribution->id);

            if ($locked->isPaid()) {
                throw ValidationException::withMessages([
                    'amount' => 'Distribusi ini sudah dibayar.',
                ]);
            }

            $amount = (float) $validated['amount'];
            $due = (float) $locked->total_pay;
            if ($amount > $due) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran melebihi jumlah distribusi anggota.',
                ]);
            }

            $category = 'biaya_tim';
            $this->assertCashOutCategoryExists($category);
            $expenseAccountId = $this->resolveCashOutAccountId($category);
            $paymentDate = $this->resolvedMemberPaymentDate($locked, (string) $validated['payment_date']);

            $recipientName = $locked->user?->name ?? 'Anggota Tim';
            $note = trim(collect([
                $validated['note'] ?? null,
                'Pembayaran anggota '.$recipientName.' — '.$locked->project?->name,
                'Peran: '.$locked->role_in_project,
            ])->filter()->implode(' | '));

            $payload = [
                'project_id' => $locked->project_id,
                'cash_account_id' => (int) $validated['cash_account_id'],
                'category' => $category,
                'amount' => $amount,
                'date' => $paymentDate,
                'note' => $note,
                'recipient_name' => $recipientName,
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
                ErpCompanyResolver::resolveForGlPosting($request),
                sourceModule: 'member_payment',
                sourceReference: (string) $locked->id,
                description: 'Pembayaran anggota '.$recipientName.' — '.($locked->project?->name ?? 'Project'),
                entryDate: $paymentDate,
                lines: [
                    ['account_id' => $expenseAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount],
                ],
            );

            $cashOut->update(['journal_entry_id' => $entry->id]);

            $locked->update([
                'cash_out_id' => $cashOut->id,
                'paid_at' => $paymentDate,
            ]);
        });

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Pembayaran anggota berhasil diposting ke kas keluar dan cashflow.',
        ]);
    }

    private function assertCashOutCategoryExists(string $category): void
    {
        if (CashCategory::isRetired('cash_out', $category)) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kas keluar ini sudah tidak digunakan.',
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

    private function resolveCashOutAccountId(string $category): int
    {
        $accountId = CategoryCoaMapping::query()
            ->where('domain', 'cash_out')
            ->where('category', $category)
            ->value('account_id');

        if (! $accountId) {
            throw ValidationException::withMessages([
                'category' => 'Kategori belum di-mapping ke akun CoA.',
            ]);
        }

        return (int) $accountId;
    }

    private function resolvedMemberPaymentDate(TeamDistribution $distribution, string $requestedDate): string
    {
        $project = $distribution->project;

        if ($project && filled($project->import_key) && $project->finished_at) {
            return $project->finished_at->toDateString();
        }

        return $requestedDate;
    }

    private function resolvedSupplierPaymentDate(Payable $payable, string $requestedDate): string
    {
        return $this->resolvedLegacySupplierPaymentDate($payable) ?? $requestedDate;
    }

    private function resolvedLegacySupplierPaymentDate(Payable $payable): ?string
    {
        $importKey = $this->extractLegacyImportKeyFromPayable($payable);
        if ($importKey === null) {
            return null;
        }

        return ProcurementImportStaging::query()
            ->where('source_import_key', $importKey)
            ->value('procurement_date')
            ?->toDateString();
    }

    private function extractLegacyImportKeyFromPayable(Payable $payable): ?string
    {
        $notes = (string) ($payable->purchaseOrder?->notes ?? '');
        if ($notes === '') {
            return null;
        }

        if (! preg_match('/Import key:\s*(.+)/i', $notes, $matches)) {
            return null;
        }

        $importKey = trim((string) ($matches[1] ?? ''));

        return $importKey !== '' ? $importKey : null;
    }
}
