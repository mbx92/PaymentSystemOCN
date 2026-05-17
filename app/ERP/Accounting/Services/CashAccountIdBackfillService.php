<?php

namespace App\ERP\Accounting\Services;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalLine;
use App\Models\CashIn;
use App\Models\CashOut;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashAccountIdBackfillService
{
    /**
     * @return array{
     *     cash_in_pending: int,
     *     cash_out_pending: int,
     *     cash_in_ready: int,
     *     cash_out_ready: int,
     *     cash_in_skipped: int,
     *     cash_out_skipped: int,
     *     cash_in_without_journal: int,
     *     cash_out_without_journal: int,
     *     samples: list<array<string, mixed>>
     * }
     */
    public function summary(int $sampleLimit = 15): array
    {
        $cashInPlan = $this->planCashIn();
        $cashOutPlan = $this->planCashOut();

        $samples = $cashInPlan['samples']
            ->concat($cashOutPlan['samples'])
            ->take($sampleLimit)
            ->values()
            ->all();

        return [
            'cash_in_pending' => $cashInPlan['pending'],
            'cash_out_pending' => $cashOutPlan['pending'],
            'cash_in_ready' => $cashInPlan['ready'],
            'cash_out_ready' => $cashOutPlan['ready'],
            'cash_in_skipped' => $cashInPlan['skipped'],
            'cash_out_skipped' => $cashOutPlan['skipped'],
            'cash_in_without_journal' => $cashInPlan['without_journal'],
            'cash_out_without_journal' => $cashOutPlan['without_journal'],
            'samples' => $samples,
        ];
    }

    /**
     * @return array{cash_in_updated: int, cash_out_updated: int}
     */
    public function apply(): array
    {
        return DB::transaction(function (): array {
            $cashInPlan = $this->planCashIn();
            $cashOutPlan = $this->planCashOut();

            foreach ($cashInPlan['updates'] as $id => $accountId) {
                CashIn::query()->whereKey($id)->update(['cash_account_id' => $accountId]);
            }

            foreach ($cashOutPlan['updates'] as $id => $accountId) {
                CashOut::query()->whereKey($id)->update(['cash_account_id' => $accountId]);
            }

            return [
                'cash_in_updated' => count($cashInPlan['updates']),
                'cash_out_updated' => count($cashOutPlan['updates']),
            ];
        });
    }

    /**
     * @return array{
     *     pending: int,
     *     ready: int,
     *     skipped: int,
     *     without_journal: int,
     *     updates: array<string, int>,
     *     samples: Collection<int, array<string, mixed>>
     * }
     */
    private function planCashIn(): array
    {
        $rows = CashIn::query()
            ->whereNull('cash_account_id')
            ->whereNotNull('journal_entry_id')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get(['id', 'journal_entry_id', 'date', 'amount', 'category', 'note']);

        $pendingWithoutJournal = (int) CashIn::query()
            ->whereNull('cash_account_id')
            ->whereNull('journal_entry_id')
            ->count();

        if ($rows->isEmpty()) {
            return [
                'pending' => $pendingWithoutJournal,
                'ready' => 0,
                'skipped' => 0,
                'without_journal' => $pendingWithoutJournal,
                'updates' => [],
                'samples' => collect(),
            ];
        }

        $debitLines = JournalLine::query()
            ->with('account:id,code,name')
            ->whereIn('journal_entry_id', $rows->pluck('journal_entry_id')->unique())
            ->where('debit', '>', 0)
            ->get()
            ->keyBy('journal_entry_id');

        $updates = [];
        $samples = collect();
        $skipped = 0;

        foreach ($rows as $row) {
            $line = $debitLines->get($row->journal_entry_id);
            if (! $line?->account_id) {
                $skipped++;

                continue;
            }

            $accountId = (int) $line->account_id;
            $updates[$row->id] = $accountId;
            if ($samples->count() < 10) {
                $samples->push($this->sampleRow('cash_in', $row, $accountId, $line->account));
            }
        }

        return [
            'pending' => $rows->count() + $pendingWithoutJournal,
            'ready' => count($updates),
            'skipped' => $skipped,
            'without_journal' => $pendingWithoutJournal,
            'updates' => $updates,
            'samples' => $samples,
        ];
    }

    /**
     * @return array{
     *     pending: int,
     *     ready: int,
     *     skipped: int,
     *     without_journal: int,
     *     updates: array<string, int>,
     *     samples: Collection<int, array<string, mixed>>
     * }
     */
    private function planCashOut(): array
    {
        $rows = CashOut::query()
            ->whereNull('cash_account_id')
            ->whereNotNull('journal_entry_id')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get(['id', 'journal_entry_id', 'date', 'amount', 'category', 'note']);

        $pendingWithoutJournal = (int) CashOut::query()
            ->whereNull('cash_account_id')
            ->whereNull('journal_entry_id')
            ->count();

        if ($rows->isEmpty()) {
            return [
                'pending' => $pendingWithoutJournal,
                'ready' => 0,
                'skipped' => 0,
                'without_journal' => $pendingWithoutJournal,
                'updates' => [],
                'samples' => collect(),
            ];
        }

        $creditAccounts = JournalLine::query()
            ->with('account:id,code,name')
            ->whereIn('journal_entry_id', $rows->pluck('journal_entry_id')->unique())
            ->where('credit', '>', 0)
            ->get(['journal_entry_id', 'account_id'])
            ->keyBy('journal_entry_id');

        $updates = [];
        $samples = collect();
        $skipped = 0;

        foreach ($rows as $row) {
            $line = $creditAccounts->get($row->journal_entry_id);
            if (! $line?->account_id) {
                $skipped++;

                continue;
            }

            $accountId = (int) $line->account_id;
            $updates[$row->id] = $accountId;
            if ($samples->count() < 10) {
                $samples->push($this->sampleRow('cash_out', $row, $accountId, $line->account));
            }
        }

        return [
            'pending' => $rows->count() + $pendingWithoutJournal,
            'ready' => count($updates),
            'skipped' => $skipped,
            'without_journal' => $pendingWithoutJournal,
            'updates' => $updates,
            'samples' => $samples,
        ];
    }

    private function sampleRow(string $domain, CashIn|CashOut $row, int $accountId, ?Account $account = null): array
    {
        $account ??= Account::query()->find($accountId);
        $label = $account
            ? $account->code.' - '.$account->name
            : (string) $accountId;

        return [
            'domain' => $domain,
            'id' => $row->id,
            'date' => $row->date?->format('Y-m-d'),
            'amount' => (float) $row->amount,
            'category' => $row->category,
            'note' => $row->note,
            'resolved_account' => $label,
        ];
    }
}
