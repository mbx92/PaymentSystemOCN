<?php

namespace App\Jobs;

use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class BackfillCashAccountsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $fromCashAccountId,
        private readonly int $toCashAccountId,
        private readonly ?string $dateFrom = null,
        private readonly ?string $dateTo = null,
        private readonly ?int $offset = 0,
        private readonly ?User $initiator = null,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $batchSize = 100;

        $query = CashIn::query()
            ->where('cash_account_id', $this->fromCashAccountId);

        if ($this->dateFrom) {
            $query->where('date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('date', '<=', $this->dateTo);
        }

        $cashInIds = (clone $query)
            ->orderBy('id')
            ->skip($this->offset)
            ->take($batchSize)
            ->pluck('id');

        if ($cashInIds->isNotEmpty()) {
            CashIn::query()->whereIn('id', $cashInIds)->update(['cash_account_id' => $this->toCashAccountId]);

            DB::table('journal_lines')
                ->whereIn('cash_in_id', $cashInIds)
                ->where('account_id', $this->fromCashAccountId)
                ->update(['account_id' => $this->toCashAccountId]);
        }

        $cashOutQuery = CashOut::query()
            ->where('cash_account_id', $this->fromCashAccountId);

        if ($this->dateFrom) {
            $cashOutQuery->where('date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $cashOutQuery->where('date', '<=', $this->dateTo);
        }

        $cashOutIds = (clone $cashOutQuery)
            ->orderBy('id')
            ->skip($this->offset)
            ->take($batchSize)
            ->pluck('id');

        if ($cashOutIds->isNotEmpty()) {
            CashOut::query()->whereIn('id', $cashOutIds)->update(['cash_account_id' => $this->toCashAccountId]);

            DB::table('journal_lines')
                ->whereIn('cash_out_id', $cashOutIds)
                ->where('account_id', $this->fromCashAccountId)
                ->update(['account_id' => $this->toCashAccountId]);
        }

        activity()
            ->causedBy($this->initiator)
            ->withProperties([
                'cash_in_updated' => $cashInIds->count(),
                'cash_out_updated' => $cashOutIds->count(),
                'offset' => $this->offset,
                'from_account_id' => $this->fromCashAccountId,
                'to_account_id' => $this->toCashAccountId,
            ])
            ->log('Cash accounts backfill batch processed');
    }
}
