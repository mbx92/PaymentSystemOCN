<?php

namespace App\Console\Commands;

use App\Services\SupplierCatalogSyncService;
use Illuminate\Console\Command;

class SyncSupplierCatalogCommand extends Command
{
    protected $signature = 'supplier-catalog:sync
        {--sheet= : Sync one sheet key only}';

    protected $description = 'Sync supplier catalog items from Google Sheets into local database';

    public function handle(SupplierCatalogSyncService $sync): int
    {
        $sheet = $this->option('sheet');

        if (is_string($sheet) && trim($sheet) !== '') {
            $result = $sync->syncSheet(trim($sheet));
            $this->info(sprintf(
                'Sheet %s synced: %d created, %d updated, %d removed.',
                $sheet,
                $result['created'],
                $result['updated'],
                $result['removed'],
            ));

            return Command::SUCCESS;
        }

        $summary = $sync->syncAll(function (string $sheetKey): void {
            $this->line("Syncing {$sheetKey}...");
        });

        $this->info(sprintf(
            'Catalog sync finished: %d sheets, %d created, %d updated, %d removed.',
            $summary['sheets'],
            $summary['created'],
            $summary['updated'],
            $summary['removed'],
        ));

        foreach ($summary['failed'] as $message) {
            $this->error($message);
        }

        return $summary['failed'] === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
