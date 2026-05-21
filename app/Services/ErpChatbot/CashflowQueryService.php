<?php

namespace App\Services\ErpChatbot;

use App\Models\CashIn;
use App\Models\CashOut;
use Carbon\CarbonInterface;

class CashflowQueryService
{
    public function summarizePeriod(string $startDate, string $endDate): array
    {
        $cashIn = (float) CashIn::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $cashOut = (float) CashOut::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net' => $cashIn - $cashOut,
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
