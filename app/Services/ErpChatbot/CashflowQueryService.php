<?php

namespace App\Services\ErpChatbot;

use App\Models\AccountingInventoryRecord;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\PosSale;
use App\ERP\Accounting\Models\PayablePayment;
use Carbon\CarbonInterface;

class CashflowQueryService
{
    public function summarizePeriod(string $startDate, string $endDate): array
    {
        $cashIn = (float) CashIn::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $posSales = (float) PosSale::query()
            ->whereNotIn('status', ['refunded'])
            ->whereBetween('sold_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->sum('grand_total');

        $posRefunds = (float) PosSale::query()
            ->where('status', 'refunded')
            ->whereBetween('sold_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->sum('grand_total');

        $cashOut = (float) CashOut::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $supplierPayments = (float) PayablePayment::query()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        $inventaris = (float) AccountingInventoryRecord::query()
            ->whereBetween('acquisition_date', [$startDate, $endDate])
            ->sum('amount');

        $totalIn = $cashIn + $posSales;
        $totalOut = $cashOut + $posRefunds + $supplierPayments + $inventaris;

        return [
            'cash_in' => $totalIn,
            'cash_out' => $totalOut,
            'net' => $totalIn - $totalOut,
            'detail' => [
                'cash_in_manual' => $cashIn,
                'pos_sales' => $posSales,
                'cash_out_manual' => $cashOut,
                'pos_refunds' => $posRefunds,
                'supplier_payments' => $supplierPayments,
                'inventaris' => $inventaris,
            ],
        ];
    }

    public function operationalComparison(CarbonInterface $now): array
    {
        $thisMonth = (float) CashOut::query()
            ->where('category', 'operasional')
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->sum('amount');

        $lastMonthDate = $now->copy()->subMonth();
        $lastMonth = (float) CashOut::query()
            ->where('category', 'operasional')
            ->whereYear('date', $lastMonthDate->year)
            ->whereMonth('date', $lastMonthDate->month)
            ->sum('amount');

        return [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'diff' => $thisMonth - $lastMonth,
        ];
    }
}
