<?php

namespace App\ERP\Accounting\Services;

use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Core\Services\FiscalPeriodService;
use App\ERP\Shared\Enums\DocumentStatus;
use App\ERP\Shared\Services\DocumentNumberService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GlPostingService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {}

    /**
     * @param  array<int, array{account_id:int, description?:string, debit:float|int|string, credit:float|int|string}>  $lines
     */
    public function post(int $companyId, string $sourceModule, string $sourceReference, string $description, string $entryDate, array $lines): JournalEntry
    {
        $this->fiscalPeriodService->ensureDateIsOpen($entryDate, $companyId, 'entry_date', 'Posting jurnal');

        // Validasi prinsip double-entry: total debit HARUS sama dengan total kredit
        $totalDebit  = collect($lines)->sum(fn (array $line): float => round((float) $line['debit'], 2));
        $totalCredit = collect($lines)->sum(fn (array $line): float => round((float) $line['credit'], 2));

        if (abs($totalDebit - $totalCredit) > 0.01) {
            abort(500, sprintf(
                'Unbalanced journal entry: total debit (%.2f) ≠ total kredit (%.2f). Source: %s / %s.',
                $totalDebit,
                $totalCredit,
                $sourceModule,
                $sourceReference
            ));
        }

        return DB::transaction(function () use ($companyId, $sourceModule, $sourceReference, $description, $entryDate, $lines): JournalEntry {
            $entryNo = $this->nextUniqueEntryNo();

            $journalEntry = JournalEntry::query()->create([
                'company_id' => $companyId,
                'entry_no' => $entryNo,
                'entry_date' => $entryDate,
                'description' => $description,
                'status' => DocumentStatus::Posted,
                'source_module' => $sourceModule,
                'source_reference' => $sourceReference,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                JournalLine::query()->create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            return $journalEntry;
        });
    }

    private function nextUniqueEntryNo(): string
    {
        $attempt = 0;
        do {
            $entryNo = $this->documentNumberService->next('accounting', 'journal_entry', [
                'prefix' => 'JE',
                'padding_length' => 6,
            ]);
            $exists = JournalEntry::query()->where('entry_no', $entryNo)->exists();
            $attempt++;
        } while ($exists && $attempt < 20);

        return $entryNo;
    }
}
