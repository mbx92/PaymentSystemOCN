<?php

namespace App\Console\Commands;

use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\JournalLine;
use App\ERP\Purchasing\Models\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UndoBackfillCommand extends Command
{
    protected $signature = 'backfill:undo';

    protected $description = 'Hapus jurnal backfill yang sudah dibuat dan kembalikan PO ke kategori inventory';

    public function handle(): int
    {
        $dryRun = $this->confirm('Jalankan dalam mode dry-run? (tidak ada perubahan)', false);

        $reclassifyEntries = JournalEntry::query()
            ->where('source_module', 'purchasing_reclassify')
            ->get();

        $cogsBackfillEntries = JournalEntry::query()
            ->where('source_module', 'pos_sale_cogs')
            ->where('description', 'like', '%(backfill)%')
            ->get();

        $materialCogsEntries = JournalEntry::query()
            ->where('source_module', 'project_material_cogs')
            ->get();

        $reclassifiedPos = PurchaseOrder::query()
            ->where('po_category', 'expense')
            ->whereHas('receipts')
            ->get();

        $this->line('');
        $this->line('=== Ringkasan yang akan dihapus ===');
        $this->line("PO Reclassify entries: {$reclassifyEntries->count()}");
        $this->line("COGS backfill entries: {$cogsBackfillEntries->count()}");
        $this->line("Material COGS backfill entries: {$materialCogsEntries->count()}");
        $this->line("PO yang akan dikembalikan ke inventory: {$reclassifiedPos->count()}");

        if ($this->confirm('Lanjutkan menghapus jurnal backfill?', true) === false) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry-run: tidak ada perubahan yang dilakukan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($reclassifyEntries, $cogsBackfillEntries, $materialCogsEntries, $reclassifiedPos): void {
            foreach ($reclassifyEntries as $entry) {
                JournalLine::query()->where('journal_entry_id', $entry->id)->delete();
                $entry->delete();
                $this->line("  Hapus jurnal: {$entry->entry_no} ({$entry->description})");
            }

            foreach ($cogsBackfillEntries as $entry) {
                JournalLine::query()->where('journal_entry_id', $entry->id)->delete();
                $entry->delete();
                $this->line("  Hapus jurnal: {$entry->entry_no} ({$entry->description})");
            }

            foreach ($materialCogsEntries as $entry) {
                JournalLine::query()->where('journal_entry_id', $entry->id)->delete();
                $entry->delete();
                $this->line("  Hapus jurnal: {$entry->entry_no} ({$entry->description})");
            }

            foreach ($reclassifiedPos as $po) {
                $po->update(['po_category' => 'inventory']);
                $this->line("  Kembalikan PO {$po->number} ke inventory");
            }
        });

        $this->info('Selesai. Semua backfill sudah dihapus.');

        return self::SUCCESS;
    }
}
