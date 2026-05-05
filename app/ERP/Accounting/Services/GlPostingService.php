<?php

namespace App\ERP\Accounting\Services;

use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Core\Models\DocumentSequence;
use App\ERP\Shared\Enums\DocumentStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GlPostingService
{
    /**
     * @param  array<int, array{account_id:int, description?:string, debit:float|int|string, credit:float|int|string}>  $lines
     */
    public function post(string $sourceModule, string $sourceReference, string $description, string $entryDate, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($sourceModule, $sourceReference, $description, $entryDate, $lines): JournalEntry {
            $sequence = DocumentSequence::query()->firstOrCreate(
                ['module' => 'accounting', 'document_type' => 'journal_entry'],
                ['prefix' => 'JE', 'running_number' => 0, 'padding_length' => 6]
            );

            $sequence->increment('running_number');

            $entryNo = sprintf(
                '%s-%s',
                $sequence->prefix,
                str_pad((string) $sequence->running_number, $sequence->padding_length, '0', STR_PAD_LEFT)
            );

            $journalEntry = JournalEntry::query()->create([
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
}
