<?php

namespace App\ERP\Accounting\Services;

use App\ERP\Accounting\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntrySideReversalService
{
    /**
     * @param  list<int>  $journalEntryIds
     * @return array{entry_count:int,line_count:int}
     */
    public function apply(array $journalEntryIds): array
    {
        $journalEntryIds = collect($journalEntryIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($journalEntryIds === []) {
            throw ValidationException::withMessages([
                'journal_entry_ids' => 'Pilih minimal satu jurnal untuk dibalik sisi debit/kreditnya.',
            ]);
        }

        return DB::transaction(function () use ($journalEntryIds): array {
            $entries = JournalEntry::query()
                ->with('lines:id,journal_entry_id,debit,credit')
                ->whereIn('id', $journalEntryIds)
                ->get();

            if ($entries->count() !== count($journalEntryIds)) {
                throw ValidationException::withMessages([
                    'journal_entry_ids' => 'Sebagian jurnal tidak ditemukan.',
                ]);
            }

            foreach ($entries as $entry) {
                if ($entry->lines->isEmpty()) {
                    throw ValidationException::withMessages([
                        'journal_entry_ids' => 'Ada jurnal tanpa baris, tidak bisa dibalik otomatis.',
                    ]);
                }

                foreach ($entry->lines as $line) {
                    $debit = round((float) $line->debit, 2);
                    $credit = round((float) $line->credit, 2);

                    if ($debit > 0 && $credit > 0) {
                        throw ValidationException::withMessages([
                            'journal_entry_ids' => 'Ada baris jurnal yang memiliki debit dan kredit sekaligus. Balik sisi otomatis dibatalkan.',
                        ]);
                    }
                }
            }

            $lineCount = 0;

            foreach ($entries as $entry) {
                foreach ($entry->lines as $line) {
                    $debit = round((float) $line->debit, 2);
                    $credit = round((float) $line->credit, 2);

                    $line->update([
                        'debit' => number_format($credit, 2, '.', ''),
                        'credit' => number_format($debit, 2, '.', ''),
                    ]);

                    $lineCount++;
                }
            }

            return [
                'entry_count' => $entries->count(),
                'line_count' => $lineCount,
            ];
        });
    }
}
