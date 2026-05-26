<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ProductionErpSyncService
{
    public function sourceConnection(): string
    {
        return (string) config('production_sync.source_connection', config('database.default'));
    }

    public function targetConnection(): string
    {
        return (string) config('production_sync.target_connection', 'production_ocn_erp');
    }

    /**
     * @return array<string, list<string>>
     */
    public function modules(): array
    {
        /** @var array<string, list<string>> $modules */
        $modules = config('production_sync.modules', []);

        return $modules;
    }

    /**
     * @return list<string>
     */
    public function allTables(): array
    {
        return collect($this->modules())
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $requestedModules
     * @param  list<string>  $requestedTables
     * @return array<string, mixed>
     */
    public function sync(array $requestedModules = [], array $requestedTables = [], bool $dryRun = true, int $chunkSize = 500): array
    {
        $chunkSize = max($chunkSize, 1);
        $source = $this->sourceConnection();
        $target = $this->targetConnection();

        $this->ensureConnectionsReady($source, $target);

        $plan = $this->resolvedPlan($requestedModules, $requestedTables);
        $summary = [
            'source_connection' => $source,
            'target_connection' => $target,
            'dry_run' => $dryRun,
            'chunk_size' => $chunkSize,
            'modules' => [],
            'tables' => [],
            'totals' => [
                'tables' => 0,
                'source_rows' => 0,
                'target_rows_before' => 0,
                'target_rows_after' => 0,
                'rows_synced' => 0,
            ],
        ];

        foreach ($plan as $module => $tables) {
            $summary['modules'][$module] = [];

            foreach ($tables as $table) {
                $tableSummary = $this->syncTable($source, $target, $table, $dryRun, $chunkSize);
                $summary['modules'][$module][$table] = $tableSummary;
                $summary['tables'][$table] = $tableSummary;
                $summary['totals']['tables']++;
                $summary['totals']['source_rows'] += (int) $tableSummary['source_rows'];
                $summary['totals']['target_rows_before'] += (int) $tableSummary['target_rows_before'];
                $summary['totals']['target_rows_after'] += (int) $tableSummary['target_rows_after'];
                $summary['totals']['rows_synced'] += (int) $tableSummary['rows_synced'];
            }
        }

        return $summary;
    }

    private function ensureConnectionsReady(string $source, string $target): void
    {
        foreach ([$source, $target] as $connection) {
            $config = (array) config('database.connections.'.$connection, []);
            $hasUrl = trim((string) ($config['url'] ?? '')) !== '';
            $hasDatabase = trim((string) ($config['database'] ?? '')) !== '';

            if (! $hasUrl && ! $hasDatabase) {
                throw new RuntimeException("Koneksi {$connection} belum dikonfigurasi.");
            }

            DB::connection($connection)->getPdo();
        }
    }

    /**
     * @param  list<string>  $requestedModules
     * @param  list<string>  $requestedTables
     * @return array<string, list<string>>
     */
    private function resolvedPlan(array $requestedModules, array $requestedTables): array
    {
        $modules = $this->modules();

        if ($requestedModules !== []) {
            $invalidModules = array_values(array_diff($requestedModules, array_keys($modules)));
            if ($invalidModules !== []) {
                throw new RuntimeException('Module tidak dikenal: '.implode(', ', $invalidModules));
            }
        }

        $allTables = $this->allTables();
        if ($requestedTables !== []) {
            $invalidTables = array_values(array_diff($requestedTables, $allTables));
            if ($invalidTables !== []) {
                throw new RuntimeException('Tabel tidak dikenal dalam plan sync: '.implode(', ', $invalidTables));
            }
        }

        $selectedModules = $requestedModules !== [] ? Arr::only($modules, $requestedModules) : $modules;
        if ($requestedTables === []) {
            return $selectedModules;
        }

        $plan = [];
        foreach ($selectedModules as $module => $tables) {
            $filtered = array_values(array_intersect($tables, $requestedTables));
            if ($filtered !== []) {
                $plan[$module] = $filtered;
            }
        }

        if ($plan === []) {
            throw new RuntimeException('Tidak ada tabel yang cocok dengan kombinasi module/table yang diminta.');
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private function syncTable(string $source, string $target, string $table, bool $dryRun, int $chunkSize): array
    {
        if (! Schema::connection($source)->hasTable($table)) {
            throw new RuntimeException("Tabel {$table} tidak ditemukan pada source {$source}.");
        }

        if (! Schema::connection($target)->hasTable($table)) {
            throw new RuntimeException("Tabel {$table} tidak ditemukan pada target {$target}.");
        }

        $sourceColumns = Schema::connection($source)->getColumnListing($table);
        $targetColumns = Schema::connection($target)->getColumnListing($table);
        $columns = array_values(array_intersect($sourceColumns, $targetColumns));

        if ($columns === []) {
            throw new RuntimeException("Tidak ada kolom yang bisa disinkronkan untuk tabel {$table}.");
        }

        if (! in_array('id', $columns, true)) {
            throw new RuntimeException("Tabel {$table} belum didukung karena tidak memiliki kolom id.");
        }

        $updateColumns = array_values(array_diff($columns, ['id']));
        $sourceRows = (int) DB::connection($source)->table($table)->count();
        $targetRowsBefore = (int) DB::connection($target)->table($table)->count();
        $rowsSynced = 0;

        if (! $dryRun && $sourceRows > 0) {
            DB::connection($source)
                ->table($table)
                ->orderBy('id')
                ->chunk($chunkSize, function (Collection $chunk) use ($target, $table, $columns, $updateColumns, &$rowsSynced): void {
                    $payload = $chunk
                        ->map(fn ($row) => Arr::only((array) $row, $columns))
                        ->all();

                    if ($payload === []) {
                        return;
                    }

                    DB::connection($target)->table($table)->upsert($payload, ['id'], $updateColumns);
                    $rowsSynced += count($payload);
                });
        } else {
            $rowsSynced = $sourceRows;
        }

        $targetRowsAfter = $dryRun
            ? $targetRowsBefore
            : (int) DB::connection($target)->table($table)->count();

        return [
            'table' => $table,
            'source_rows' => $sourceRows,
            'target_rows_before' => $targetRowsBefore,
            'target_rows_after' => $targetRowsAfter,
            'rows_synced' => $rowsSynced,
            'mode' => $dryRun ? 'dry-run' : 'upsert',
        ];
    }
}
