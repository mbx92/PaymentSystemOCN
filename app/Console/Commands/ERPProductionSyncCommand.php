<?php

namespace App\Console\Commands;

use App\Services\ProductionErpSyncService;
use Illuminate\Console\Command;

class ERPProductionSyncCommand extends Command
{
    protected $signature = 'erp:production-sync
        {--module=* : Sync only specific module(s) from config/production_sync.php}
        {--table=* : Sync only specific table(s)}
        {--chunk=500 : Chunk size per upsert batch}
        {--execute : Actually write to production target. Default is dry-run only.}';

    protected $description = 'Dry-run or execute staged import from local OCN ERP DB into production OCN ERP DB.';

    public function handle(ProductionErpSyncService $syncService): int
    {
        $modules = collect((array) $this->option('module'))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $tables = collect((array) $this->option('table'))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $chunkSize = max((int) $this->option('chunk'), 1);
        $dryRun = ! $this->option('execute');

        try {
            $summary = $syncService->sync($modules, $tables, $dryRun, $chunkSize);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('ERP Production Sync');
        $this->line('Source: '.$summary['source_connection']);
        $this->line('Target: '.$summary['target_connection']);
        $this->line('Mode: '.($summary['dry_run'] ? 'dry-run' : 'execute'));
        $this->line('Chunk: '.number_format((int) $summary['chunk_size']));
        $this->newLine();

        foreach ($summary['modules'] as $module => $tablesSummary) {
            $this->comment('Module: '.$module);
            $this->table(
                ['Table', 'Source', 'Target Before', 'Target After', 'Rows Synced', 'Mode'],
                collect($tablesSummary)->map(fn (array $row) => [
                    $row['table'],
                    number_format((int) $row['source_rows']),
                    number_format((int) $row['target_rows_before']),
                    number_format((int) $row['target_rows_after']),
                    number_format((int) $row['rows_synced']),
                    $row['mode'],
                ])->values()->all()
            );
        }

        $totals = $summary['totals'];
        $this->info('Summary');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tables', number_format((int) $totals['tables'])],
                ['Source Rows', number_format((int) $totals['source_rows'])],
                ['Target Rows Before', number_format((int) $totals['target_rows_before'])],
                ['Target Rows After', number_format((int) $totals['target_rows_after'])],
                ['Rows Synced', number_format((int) $totals['rows_synced'])],
            ]
        );

        if ($summary['dry_run']) {
            $this->warn('Dry-run only. Tambahkan --execute setelah rekonsiliasi modul/tabel lolos.');
        } else {
            $this->warn('Sinkronisasi execute selesai. Lanjutkan rekonsiliasi hasil sebelum modul berikutnya.');
        }

        return self::SUCCESS;
    }
}
