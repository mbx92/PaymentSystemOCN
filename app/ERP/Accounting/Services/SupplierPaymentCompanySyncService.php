<?php

namespace App\ERP\Accounting\Services;

use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\PayablePayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierPaymentCompanySyncService
{
    public function summary(?int $companyId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $rows = $this->candidateRows($companyId, $dateFrom, $dateTo);

        return [
            'candidate_count' => $rows->count(),
            'entry_count' => $rows->pluck('journal_entry_id')->unique()->count(),
            'samples' => $rows->take(8)->values()->all(),
        ];
    }

    public function apply(?int $companyId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $rows = $this->candidateRows($companyId, $dateFrom, $dateTo);
        $targetMap = $rows
            ->groupBy('journal_entry_id')
            ->map(fn (Collection $group): int => (int) $group->first()['expected_company_id']);

        DB::transaction(function () use ($targetMap): void {
            foreach ($targetMap as $journalEntryId => $companyId) {
                JournalEntry::query()
                    ->whereKey((int) $journalEntryId)
                    ->update(['company_id' => (int) $companyId]);
            }
        });

        return [
            'candidate_count' => $rows->count(),
            'entry_count' => $targetMap->count(),
        ];
    }

    /**
     * @return Collection<int, array{
     *     payment_id:int,
     *     journal_entry_id:int,
     *     payment_date:string,
     *     amount:float,
     *     bill_no:string,
     *     current_company_id:int|null,
     *     current_company_name:string,
     *     expected_company_id:int,
     *     expected_company_name:string
     * }>
     */
    private function candidateRows(?int $companyId = null, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        $rows = PayablePayment::query()
            ->with([
                'journalEntry.company:id,name',
                'payable.journalEntry.company:id,name',
                'payable.goodsReceipt.warehouse.company:id,name',
            ])
            ->when($dateFrom, fn ($query) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('payment_date', '<=', $dateTo))
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->map(function (PayablePayment $payment): ?array {
                $expectedCompanyId = $payment->payable?->journalEntry?->company_id
                    ?? $payment->payable?->goodsReceipt?->warehouse?->company_id;

                if (! $expectedCompanyId) {
                    return null;
                }

                $currentCompanyId = $payment->journalEntry?->company_id;
                if ((int) $currentCompanyId === (int) $expectedCompanyId) {
                    return null;
                }

                $expectedCompanyName = $payment->payable?->journalEntry?->company?->name
                    ?? $payment->payable?->goodsReceipt?->warehouse?->company?->name
                    ?? 'Belum ditentukan';

                return [
                    'payment_id' => (int) $payment->id,
                    'journal_entry_id' => (int) $payment->journal_entry_id,
                    'payment_date' => $payment->payment_date?->toDateString() ?? now()->toDateString(),
                    'amount' => (float) $payment->amount,
                    'bill_no' => (string) ($payment->payable?->bill_no ?? '-'),
                    'current_company_id' => $currentCompanyId ? (int) $currentCompanyId : null,
                    'current_company_name' => $payment->journalEntry?->company?->name ?? 'Belum ditentukan',
                    'expected_company_id' => (int) $expectedCompanyId,
                    'expected_company_name' => (string) $expectedCompanyName,
                ];
            })
            ->filter()
            ->values();

        if (! $companyId) {
            return $rows;
        }

        return $rows
            ->filter(fn (array $row): bool => (int) ($row['current_company_id'] ?? 0) === $companyId || (int) $row['expected_company_id'] === $companyId)
            ->values();
    }
}
