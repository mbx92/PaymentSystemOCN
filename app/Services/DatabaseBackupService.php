<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

class DatabaseBackupService
{
    public function downloadPostgresDump(?string $connectionName = null): BinaryFileResponse
    {
        $config = $this->postgresConfig($connectionName);
        if (! $this->binaryAvailable($config['binary'])) {
            throw new RuntimeException('Binary pg_dump tidak ditemukan di server aplikasi.');
        }

        $filename = 'backup-database-'.$config['database'].'-'.now()->format('Ymd-His').'.sql';
        $tempPath = tempnam(sys_get_temp_dir(), 'ocn-pg-dump-');
        if ($tempPath === false) {
            throw new RuntimeException('Gagal menyiapkan file sementara untuk backup database.');
        }

        $process = new Process($this->pgDumpCommand($config, $tempPath), null, $this->pgDumpEnvironment($config), null, 3600);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($tempPath) || filesize($tempPath) === 0) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            Log::error('pg_dump backup failed.', [
                'database' => $config['database'],
                'host' => $config['host'],
                'port' => $config['port'],
                'schema' => $config['schema'],
                'binary' => $config['binary'],
                'exit_code' => $process->getExitCode(),
                'error_output' => trim($process->getErrorOutput()),
                'output' => trim($process->getOutput()),
            ]);

            throw new RuntimeException(
                'pg_dump gagal dijalankan. Periksa koneksi database, credential, dan permission container aplikasi.'
            );
        }

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }

    public function backupMeta(?string $connectionName = null): array
    {
        try {
            $config = $this->postgresConfig($connectionName);
            $binaryAvailable = $this->binaryAvailable($config['binary']);

            return [
                'connection' => $config['connection'],
                'driver' => $config['driver'],
                'database' => $config['database'],
                'host' => $config['host'],
                'port' => $config['port'],
                'schema' => $config['schema'],
                'format' => 'PostgreSQL pg_dump (.sql)',
                'binary' => $config['binary'],
                'binary_exists' => $binaryAvailable,
                'available' => $binaryAvailable,
                'message' => $binaryAvailable
                    ? 'Backup akan dihasilkan langsung dari pg_dump di server.'
                    : 'Binary pg_dump tidak ditemukan di server aplikasi.',
            ];
        } catch (RuntimeException $e) {
            return [
                'connection' => DB::getDefaultConnection(),
                'driver' => DB::connection($connectionName)->getDriverName(),
                'database' => DB::connection($connectionName)->getDatabaseName(),
                'format' => 'PostgreSQL pg_dump (.sql)',
                'available' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{
     *   connection:string,
     *   driver:string,
     *   database:string,
     *   host:string,
     *   port:string,
     *   username:string,
     *   password:?string,
     *   schema:string,
     *   binary:string
     * }
     */
    private function postgresConfig(?string $connectionName = null): array
    {
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();

        if ($driver !== 'pgsql') {
            throw new RuntimeException('Backup pg_dump hanya tersedia untuk koneksi PostgreSQL.');
        }

        $config = config('database.connections.'.$connection->getName(), []);
        $binary = $this->resolvePgDumpBinary();

        return [
            'connection' => $connection->getName(),
            'driver' => $driver,
            'database' => (string) ($config['database'] ?? $connection->getDatabaseName()),
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? '5432'),
            'username' => (string) ($config['username'] ?? ''),
            'password' => Arr::get($config, 'password'),
            'schema' => (string) ($config['schema'] ?? 'public'),
            'binary' => $binary,
        ];
    }

    /**
     * @param  array{
     *   database:string,
     *   host:string,
     *   port:string,
     *   username:string,
     *   schema:string,
     *   binary:string
     * }  $config
     * @return list<string>
     */
    private function pgDumpCommand(array $config, string $outputPath): array
    {
        return [
            $config['binary'],
            '--dbname='.$config['database'],
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            '--schema='.$config['schema'],
            '--format=plain',
            '--encoding=UTF8',
            '--no-owner',
            '--no-privileges',
            '--clean',
            '--if-exists',
            '--file='.$outputPath,
        ];
    }

    /**
     * @param  array{password:?string}  $config
     * @return array<string, string>
     */
    private function pgDumpEnvironment(array $config): array
    {
        $env = $_ENV;
        $env['PGPASSWORD'] = (string) ($config['password'] ?? '');

        return $env;
    }

    private function looksLikeAbsoluteBinaryPath(string $binary): bool
    {
        return str_starts_with($binary, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $binary) === 1;
    }

    private function binaryAvailable(string $binary): bool
    {
        return $this->looksLikeAbsoluteBinaryPath($binary)
            ? file_exists($binary)
            : (new ExecutableFinder())->find($binary) !== null;
    }

    private function resolvePgDumpBinary(): string
    {
        $configured = trim((string) config('database.pg_dump_binary', env('PG_DUMP_BINARY', '')));
        if ($configured !== '') {
            if ($this->looksLikeAbsoluteBinaryPath($configured)) {
                if (! file_exists($configured)) {
                    throw new RuntimeException('Binary pg_dump tidak ditemukan di path yang dikonfigurasi.');
                }

                return $configured;
            }

            $foundConfigured = (new ExecutableFinder())->find($configured);
            if ($foundConfigured !== null) {
                return $foundConfigured;
            }
        }

        $finder = new ExecutableFinder();
        $fromPath = $finder->find('pg_dump');
        if ($fromPath !== null) {
            return $fromPath;
        }

        foreach ($this->commonPgDumpCandidates() as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Binary pg_dump tidak ditemukan di server aplikasi. Set env PG_DUMP_BINARY jika path-nya custom.');
    }

    /**
     * @return list<string>
     */
    private function commonPgDumpCandidates(): array
    {
        $home = (string) env('HOME', '');

        return array_values(array_filter([
            '/usr/local/bin/pg_dump',
            '/usr/local/opt/postgresql/bin/pg_dump',
            '/usr/local/opt/postgresql@17/bin/pg_dump',
            '/usr/local/opt/postgresql@16/bin/pg_dump',
            '/usr/local/opt/libpq/bin/pg_dump',
            '/opt/homebrew/bin/pg_dump',
            '/opt/homebrew/opt/postgresql/bin/pg_dump',
            '/opt/homebrew/opt/postgresql@17/bin/pg_dump',
            '/opt/homebrew/opt/postgresql@16/bin/pg_dump',
            '/opt/homebrew/opt/libpq/bin/pg_dump',
            '/Applications/Postgres.app/Contents/Versions/latest/bin/pg_dump',
            $home !== '' ? $home.'/.local/bin/pg_dump' : null,
        ]));
    }
}
