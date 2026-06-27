<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function __construct(
        private readonly GeneratedFileArchiveService $generatedFileArchiveService,
    ) {}

    public function downloadPostgresDump(?string $connectionName = null): BinaryFileResponse
    {
        $dump = $this->createPostgresDump($connectionName);

        $this->generatedFileArchiveService->archiveLocalFile(
            $dump['path'],
            GeneratedFileArchiveService::CATEGORY_DATABASE,
            $dump['filename'],
            'application/sql; charset=UTF-8',
        );

        return response()->download($dump['path'], $dump['filename'], [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return array{path: string, filename: string}
     */
    public function createPostgresDump(?string $connectionName = null): array
    {
        $config = $this->postgresConfig($connectionName);
        if (! $this->binaryAvailable($config['binary'])) {
            throw new RuntimeException('Binary pg_dump tidak ditemukan di server aplikasi.');
        }

        $filename = 'backup-database-'.$config['database'].'-'.now()->format('Ymd-His').'.sql';
        $tempPath = $this->temporaryBackupPath($filename);

        $process = new Process($this->pgDumpCommand($config, $tempPath), null, $this->pgDumpEnvironment($config), null, 3600);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($tempPath) || filesize($tempPath) === 0) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            $errorOutput = trim($process->getErrorOutput());
            $output = trim($process->getOutput());

            Log::error('pg_dump backup failed.', [
                'database' => $config['database'],
                'host' => $config['host'],
                'port' => $config['port'],
                'schema' => $config['schema'],
                'binary' => $config['binary'],
                'exit_code' => $process->getExitCode(),
                'error_output' => $errorOutput,
                'output' => $output,
            ]);

            $detail = $this->summarizeProcessFailure($errorOutput, $output, $process->getExitCode());

            throw new RuntimeException('pg_dump gagal dijalankan. '.$detail);
        }

        return [
            'path' => $tempPath,
            'filename' => $filename,
        ];
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
        $binary = $this->resolvePgDumpBinary($this->postgresServerMajorVersion($connectionName));

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
            : (new ExecutableFinder)->find($binary) !== null;
    }

    private function resolvePgDumpBinary(?int $serverMajor = null): string
    {
        $configured = trim((string) config('database.pg_dump_binary', env('PG_DUMP_BINARY', '')));
        if ($configured !== '') {
            if ($this->looksLikeAbsoluteBinaryPath($configured)) {
                if (! file_exists($configured)) {
                    throw new RuntimeException('Binary pg_dump tidak ditemukan di path yang dikonfigurasi.');
                }

                return $configured;
            }

            $foundConfigured = (new ExecutableFinder)->find($configured);
            if ($foundConfigured !== null) {
                return $foundConfigured;
            }
        }

        foreach ($this->preferredPgDumpCandidates($serverMajor) as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        $finder = new ExecutableFinder;
        $fromPath = $finder->find('pg_dump');
        if ($fromPath !== null) {
            return $fromPath;
        }

        foreach ($this->commonPgDumpCandidates() as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Binary pg_dump tidak ditemukan di server aplikasi.'
            .($serverMajor ? " Install pg_dump versi {$serverMajor} atau set env PG_DUMP_BINARY." : ' Set env PG_DUMP_BINARY jika path-nya custom.')
        );
    }

    /**
     * @return list<string>
     */
    private function commonPgDumpCandidates(): array
    {
        $home = (string) env('HOME', '');

        return array_values(array_filter([
            '/usr/lib/postgresql/17/bin/pg_dump',
            '/usr/lib/postgresql/16/bin/pg_dump',
            '/usr/lib/postgresql/15/bin/pg_dump',
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

    /**
     * @return list<string>
     */
    private function preferredPgDumpCandidates(?int $serverMajor): array
    {
        if (! $serverMajor) {
            return [];
        }

        return array_values(array_filter([
            "/usr/lib/postgresql/{$serverMajor}/bin/pg_dump",
            "/usr/local/opt/postgresql@{$serverMajor}/bin/pg_dump",
            "/opt/homebrew/opt/postgresql@{$serverMajor}/bin/pg_dump",
        ]));
    }

    private function postgresServerMajorVersion(?string $connectionName = null): ?int
    {
        try {
            $versionNum = (string) DB::connection($connectionName)
                ->selectOne("select current_setting('server_version_num') as version_num")
                ?->version_num;

            if ($versionNum === '' || ! ctype_digit($versionNum)) {
                return null;
            }

            return (int) floor(((int) $versionNum) / 10000);
        } catch (\Throwable) {
            return null;
        }
    }

    private function temporaryBackupPath(string $filename): string
    {
        $directory = storage_path('app/tmp-backups');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Gagal membuat direktori sementara backup database.');
        }

        if (! is_writable($directory)) {
            throw new RuntimeException('Direktori sementara backup database tidak bisa ditulis.');
        }

        return $directory.'/'.Str::uuid()->toString().'-'.$filename;
    }

    private function summarizeProcessFailure(string $errorOutput, string $output, ?int $exitCode): string
    {
        $detail = $errorOutput !== '' ? $errorOutput : $output;
        $detail = preg_replace('/\s+/', ' ', $detail ?? '') ?? '';
        $detail = trim($detail);

        if ($detail === '') {
            return 'Periksa koneksi database, credential, permission container aplikasi, dan ketersediaan binary pg_dump.'
                .($exitCode !== null ? ' Exit code: '.$exitCode.'.' : '');
        }

        if (str_contains(strtolower($detail), 'server version mismatch')) {
            return 'Detail: '.$detail.'. Install pg_dump dengan major version yang sama seperti server PostgreSQL, atau arahkan env PG_DUMP_BINARY ke binary yang cocok'
                .($exitCode !== null ? ' (exit code '.$exitCode.')' : '');
        }

        $detail = Str::limit($detail, 240);

        return 'Detail: '.$detail.($exitCode !== null ? ' (exit code '.$exitCode.')' : '');
    }
}
