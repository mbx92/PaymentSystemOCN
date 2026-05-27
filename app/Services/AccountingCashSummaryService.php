<?php

namespace App\Services;

use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\PayablePayment;
use App\Models\AccountingInventoryRecord;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\PosSale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountingCashSummaryService
{
    /**
     * @return array{cash_in: float, cash_out: float, net_cashflow: float}
     */
    public function totals(?int $companyId = null, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $posRows = $this->posRows($companyId, $dateFrom, $dateTo);
        $supplierPaymentRows = $this->supplierPaymentRows($companyId, $dateFrom, $dateTo);
        $inventoryOutflowRows = $this->inventoryOutflowRows($companyId, $dateFrom, $dateTo);

        $cashIn = (float) $this->applyDateRange($this->cashInBaseQuery($companyId), $dateFrom, $dateTo)->sum('amount')
            + (float) $posRows->where('direction', 'in')->sum('amount');

        $cashOut = (float) $this->applyDateRange($this->cashOutBaseQuery($companyId), $dateFrom, $dateTo)->sum('amount')
            + (float) $posRows->where('direction', 'out')->sum('amount')
            + (float) $supplierPaymentRows->sum('amount')
            + (float) $inventoryOutflowRows->sum('amount');

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cashflow' => $cashIn - $cashOut,
        ];
    }

    /**
     * @return list<array{month:int,income:float,expense:float,net:float}>
     */
    public function monthlyData(int $year, ?int $companyId = null): array
    {
        $dateFrom = Carbon::create($year, 1, 1)->startOfDay();
        $dateTo = Carbon::create($year, 12, 31)->endOfDay();
        $posRows = $this->posRows($companyId, $dateFrom, $dateTo);
        $supplierPaymentRows = $this->supplierPaymentRows($companyId, $dateFrom, $dateTo);
        $inventoryOutflowRows = $this->inventoryOutflowRows($companyId, $dateFrom, $dateTo);

        $incomeRows = $this->applyDateRange($this->cashInBaseQuery($companyId), $dateFrom, $dateTo)
            ->get(['date', 'amount']);
        $expenseRows = $this->applyDateRange($this->cashOutBaseQuery($companyId), $dateFrom, $dateTo)
            ->get(['date', 'amount']);

        $incomeByMonth = $incomeRows
            ->groupBy(fn ($row) => (int) $row->date?->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
        $expenseByMonth = $expenseRows
            ->groupBy(fn ($row) => (int) $row->date?->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $posIncomeByMonth = $posRows
            ->where('direction', 'in')
            ->groupBy(fn (array $row) => (int) Carbon::parse($row['date'])->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
        $posExpenseByMonth = $posRows
            ->where('direction', 'out')
            ->groupBy(fn (array $row) => (int) Carbon::parse($row['date'])->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
        $supplierExpenseByMonth = $supplierPaymentRows
            ->groupBy(fn (array $row) => (int) Carbon::parse($row['date'])->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
        $inventoryExpenseByMonth = $inventoryOutflowRows
            ->groupBy(fn (array $row) => (int) Carbon::parse($row['date'])->format('n'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        return collect(range(1, 12))
            ->map(function (int $month) use ($incomeByMonth, $expenseByMonth, $posIncomeByMonth, $posExpenseByMonth, $supplierExpenseByMonth, $inventoryExpenseByMonth): array {
                $income = (float) ($incomeByMonth[$month] ?? 0) + (float) ($posIncomeByMonth[$month] ?? 0);
                $expense = (float) ($expenseByMonth[$month] ?? 0)
                    + (float) ($posExpenseByMonth[$month] ?? 0)
                    + (float) ($supplierExpenseByMonth[$month] ?? 0)
                    + (float) ($inventoryExpenseByMonth[$month] ?? 0);

                return [
                    'month' => $month,
                    'income' => $income,
                    'expense' => $expense,
                    'net' => $income - $expense,
                ];
            })
            ->all();
    }

    private function cashInBaseQuery(?int $companyId)
    {
        return CashIn::query()
            ->when($companyId, fn (Builder $q) => $this->applyCompanyScope($q, $companyId));
    }

    private function cashOutBaseQuery(?int $companyId)
    {
        return CashOut::query()
            ->when($companyId, fn (Builder $q) => $this->applyCompanyScope($q, $companyId));
    }

    private function applyCompanyScope(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $scope) use ($companyId): void {
            $scope->whereHas('journalEntry', fn (Builder $journal) => $journal->where('company_id', $companyId))
                ->orWhere(function (Builder $fallback) use ($companyId): void {
                    $fallback->whereHas('creator', fn (Builder $creator) => $creator->where('company_id', $companyId))
                        ->where(function (Builder $journalState): void {
                            $journalState->whereNull('journal_entry_id')
                                ->orWhereHas('journalEntry', fn (Builder $journal) => $journal->whereNull('company_id'));
                        });
                });
        });
    }

    private function applyDateRange($query, ?Carbon $dateFrom, ?Carbon $dateTo)
    {
        return $query
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo->toDateString()));
    }

    /**
     * @return Collection<int, array{direction: string, amount: float, date: string|null}>
     */
    private function posRows(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        $sales = PosSale::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('sold_at', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('sold_at', '<=', $dateTo->toDateString()))
            ->get(['id', 'number', 'grand_total', 'status', 'sold_at']);

        if ($sales->isEmpty()) {
            return collect();
        }

        $journalMap = JournalEntry::query()
            ->whereIn('source_module', ['pos_sale', 'pos_sale_refund', 'pos_sale_reopen'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('source_reference', $sales->pluck('number')->all())
            ->get(['id', 'source_module', 'source_reference'])
            ->keyBy(fn (JournalEntry $entry) => $entry->source_module.'|'.$entry->source_reference);

        return $sales
            ->map(function (PosSale $sale) use ($companyId, $journalMap): ?array {
                $status = (string) $sale->status;
                $direction = $status === 'refunded' ? 'out' : 'in';
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
                    'direction' => $direction,
                    'amount' => (float) $sale->grand_total,
                    'date' => $sale->sold_at?->toDateString(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{amount: float, date: string|null}>
     */
    private function supplierPaymentRows(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        return PayablePayment::query()
            ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn (Builder $journal) => $journal->where('company_id', $companyId)))
            ->when($dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $dateTo->toDateString()))
            ->get(['amount', 'payment_date'])
            ->map(fn (PayablePayment $payment): array => [
                'amount' => (float) $payment->amount,
                'date' => $payment->payment_date?->toDateString(),
            ]);
    }

    /**
     * @return Collection<int, array{amount: float, date: string|null}>
     */
    private function inventoryOutflowRows(?int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        return AccountingInventoryRecord::query()
            ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn (Builder $journal) => $journal->where('company_id', $companyId)))
            ->when($dateFrom, fn ($q) => $q->whereDate('acquisition_date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('acquisition_date', '<=', $dateTo->toDateString()))
            ->get(['amount', 'acquisition_date'])
            ->map(fn (AccountingInventoryRecord $record): array => [
                'amount' => (float) $record->amount,
                'date' => $record->acquisition_date?->toDateString(),
            ]);
    }
}
