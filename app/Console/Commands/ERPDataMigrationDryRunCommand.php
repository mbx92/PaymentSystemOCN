<?php

namespace App\Console\Commands;

use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\Project;
use Illuminate\Console\Command;

class ERPDataMigrationDryRunCommand extends Command
{
    protected $signature = 'erp:migration-dry-run';

    protected $description = 'Run dry-run checks for ERP cutover reconciliation';

    public function handle(): int
    {
        $projectCount = Project::query()->count();
        $cashInCount = CashIn::query()->count();
        $cashOutCount = CashOut::query()->count();
        $totalCashIn = (float) CashIn::query()->sum('amount');
        $totalCashOut = (float) CashOut::query()->sum('amount');
        $netCash = $totalCashIn - $totalCashOut;

        $this->info('ERP Migration Dry Run');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Projects', number_format($projectCount)],
                ['Cash In Transactions', number_format($cashInCount)],
                ['Cash Out Transactions', number_format($cashOutCount)],
                ['Total Cash In', number_format($totalCashIn, 2)],
                ['Total Cash Out', number_format($totalCashOut, 2)],
                ['Net Cash', number_format($netCash, 2)],
            ]
        );

        $this->warn('Checklist: validate opening balances, AR/AP outstanding, and GL postings before go-live.');

        return self::SUCCESS;
    }
}
