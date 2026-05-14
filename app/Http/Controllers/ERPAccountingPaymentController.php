<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Shared\Enums\DocumentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ERPAccountingPaymentController extends Controller
{
    public function __construct(private readonly GlPostingService $glPostingService) {}

    public function index(): Response
    {
        $payables = Payable::query()
            ->with(['vendor:id,code,name', 'purchaseOrder:id,number', 'goodsReceipt:id,number', 'payments.cashAccount:id,code,name'])
            ->orderByRaw('(amount - paid_amount) desc')
            ->orderBy('due_date')
            ->get()
            ->map(function (Payable $payable): array {
                $amount = (float) $payable->amount;
                $paid = (float) $payable->paid_amount;

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

        return Inertia::render('ERP/Accounting/Payments', [
            'payables' => $payables,
            'summary' => [
                'payables_total' => (float) $payables->sum('amount'),
                'paid_total' => (float) $payables->sum('paid_amount'),
                'outstanding_total' => (float) $payables->sum('outstanding_amount'),
                'open_count' => $payables->filter(fn (array $row) => $row['outstanding_amount'] > 0)->count(),
            ],
            'cashAccounts' => Account::query()
                ->where('is_active', true)
                ->where('type', 'asset')
                ->where('code', 'like', '100%')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function storeSupplierPayment(Request $request, Payable $payable): RedirectResponse
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'cash_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('type', 'asset')
                    ->where('is_active', true)),
            ],
            'note' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($payable, $validated): void {
            $lockedPayable = Payable::query()
                ->with('vendor')
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

            $entry = $this->glPostingService->post(
                sourceModule: 'supplier_payment',
                sourceReference: $lockedPayable->bill_no,
                description: 'Pembayaran supplier '.$lockedPayable->bill_no.' - '.($lockedPayable->vendor?->name ?? 'Supplier'),
                entryDate: $validated['payment_date'],
                lines: [
                    ['account_id' => $payableAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount],
                ],
            );

            PayablePayment::query()->create([
                'payable_id' => $lockedPayable->id,
                'payment_date' => $validated['payment_date'],
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
}
