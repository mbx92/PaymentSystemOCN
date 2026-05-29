<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Sentry\SentrySdk;
use Throwable;

class ServerMetricsService
{
    private const HISTORY_CACHE_KEY = 'server_metrics:history:v1';

    private const HISTORY_LIMIT = 30;

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $collectedAt = now();
        $connName = (string) config('database.default');
        $config = config("database.connections.{$connName}", []);
        $driver = (string) ($config['driver'] ?? 'unknown');

        $system = $this->systemMetrics();
        $history = $this->appendHistory($system);

        return [
            'collected_at' => $collectedAt->toIso8601String(),
            'collected_at_display' => $collectedAt->translatedFormat('d M Y H:i:s'),
            'collected_at_human' => $collectedAt->diffForHumans(),
            'timezone' => config('app.timezone'),
            'timezone_offset' => $collectedAt->format('P'),
            'app' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
            'database' => $this->databaseMetrics($connName, $driver, $config),
            'network' => $this->networkMetrics(),
            'system' => $system,
            'history' => $history,
            'storage' => $this->storageMetrics(),
            'glitchtip' => $this->glitchtipMetrics(),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function databaseMetrics(string $connName, string $driver, array $config): array
    {
        $host = isset($config['host']) ? (string) $config['host'] : null;
        $port = isset($config['port']) ? (int) $config['port'] : null;

        $queryMs = null;
        $queryError = null;
        try {
            $t0 = microtime(true);
            match ($driver) {
                'pgsql' => DB::connection()->select('SELECT 1 AS ok'),
                'mysql', 'mariadb' => DB::connection()->select('SELECT 1 AS ok'),
                'sqlite' => DB::connection()->select('SELECT 1 AS ok'),
                default => DB::connection()->select('SELECT 1 AS ok'),
            };
            $queryMs = round((microtime(true) - $t0) * 1000, 3);
        } catch (Throwable $e) {
            $queryError = $e->getMessage();
        }

        $tcpMs = null;
        $tcpError = null;
        if ($driver !== 'sqlite' && $host !== null && $port !== null && $port > 0) {
            try {
                $tcpMs = $this->tcpConnectMs($host, $port);
            } catch (Throwable $e) {
                $tcpError = $e->getMessage();
            }
        }

        $sizeBytes = null;
        $sizeHuman = null;
        $sizeError = null;
        try {
            [$sizeBytes, $sizeHuman] = $this->databaseSize($driver);
        } catch (Throwable $e) {
            $sizeError = $e->getMessage();
        }

        return [
            'connection' => $connName,
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => isset($config['database']) ? (string) $config['database'] : null,
            'query_latency_ms' => $queryMs,
            'query_error' => $queryError,
            'tcp_connect_ms' => $tcpMs,
            'tcp_error' => $tcpError,
            'size_bytes' => $sizeBytes,
            'size_human' => $sizeHuman,
            'size_error' => $sizeError,
        ];
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function databaseSize(string $driver): array
    {
        return match ($driver) {
            'pgsql' => $this->pgsqlDatabaseSize(),
            'mysql', 'mariadb' => $this->mysqlDatabaseSize(),
            'sqlite' => $this->sqliteDatabaseSize(),
            default => [null, null],
        };
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function pgsqlDatabaseSize(): array
    {
        $row = DB::connection()->selectOne('SELECT pg_database_size(current_database()) AS bytes');
        $bytes = (int) ($row->bytes ?? 0);
        $human = $this->formatBytes($bytes);

        return [$bytes, $human];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function mysqlDatabaseSize(): array
    {
        $row = DB::connection()->selectOne(
            'SELECT COALESCE(SUM(data_length + index_length), 0) AS bytes
             FROM information_schema.tables
             WHERE table_schema = DATABASE()'
        );
        $bytes = (int) ($row->bytes ?? 0);
        $human = $this->formatBytes($bytes);

        return [$bytes, $human];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function sqliteDatabaseSize(): array
    {
        $path = (string) config('database.connections.sqlite.database');
        if ($path === '' || $path === ':memory:') {
            throw new RuntimeException('Basis data SQLite in-memory atau path kosong.');
        }
        if (! str_contains($path, DIRECTORY_SEPARATOR) && ! str_starts_with($path, '/')) {
            $path = database_path($path);
        }
        if (! is_file($path)) {
            throw new RuntimeException('File SQLite tidak ditemukan.');
        }
        $bytes = (int) filesize($path);
        $human = $this->formatBytes($bytes);

        return [$bytes, $human];
    }

    private function tcpConnectMs(string $host, int $port, float $timeoutSec = 2.5): ?float
    {
        $target = strtolower($host) === 'localhost' ? '127.0.0.1' : $host;
        $errno = 0;
        $errstr = '';
        $t0 = microtime(true);
        $fp = @fsockopen($target, $port, $errno, $errstr, $timeoutSec);
        $ms = round((microtime(true) - $t0) * 1000, 3);
        if (is_resource($fp)) {
            fclose($fp);
        }
        if ($fp === false) {
            throw new RuntimeException($errstr !== '' ? "TCP {$target}:{$port} — {$errstr} ({$errno})" : "TCP {$target}:{$port} gagal.");
        }

        return $ms;
    }

    /**
     * @return array<string, mixed>
     */
    private function networkMetrics(): array
    {
        $url = 'https://one.one.one.one/cdn-cgi/trace';
        $ms = null;
        $error = null;
        try {
            $t0 = microtime(true);
            Http::timeout(5)
                ->connectTimeout(3)
                ->withHeaders(['User-Agent' => 'PaymentSystemOCN-ServerMetrics/1.0'])
                ->get($url);
            $ms = round((microtime(true) - $t0) * 1000, 3);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'outbound_http_ms' => $ms,
            'outbound_target' => $url,
            'outbound_error' => $error,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 0) {
            $bytes = 0;
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }

        return round($v, $i === 0 ? 0 : 2).' '.$units[$i];
    }

    /**
     * @return array<string, mixed>
     */
    private function systemMetrics(): array
    {
        $procCount = null;
        $procError = null;
        try {
            $procCount = $this->processCount();
        } catch (Throwable $e) {
            $procError = $e->getMessage();
        }

        $memoryError = null;
        try {
            $memory = $this->serverMemorySummary();
        } catch (Throwable $e) {
            $memory = [
                'php_usage_bytes' => memory_get_usage(true),
                'php_usage_human' => $this->formatBytes((int) memory_get_usage(true)),
                'php_peak_bytes' => memory_get_peak_usage(true),
                'php_peak_human' => $this->formatBytes((int) memory_get_peak_usage(true)),
            ];
            $memoryError = $e->getMessage();
        }
        $memoryUsagePct = null;
        if (isset($memory['used_bytes'], $memory['total_bytes']) && (int) $memory['total_bytes'] > 0) {
            $memoryUsagePct = round((((int) $memory['used_bytes']) / ((int) $memory['total_bytes'])) * 100, 2);
        }

        return [
            'process_count' => $procCount,
            'process_error' => $procError,
            'load_avg_1m' => function_exists('sys_getloadavg') ? round((float) (sys_getloadavg()[0] ?? 0), 3) : null,
            'memory_usage_pct' => $memoryUsagePct,
            'memory' => $memory,
            'memory_error' => $memoryError,
        ];
    }

    /**
     * @return array{labels: list<string>, memory_usage_pct: list<float>, process_count: list<int>}
     */
    private function appendHistory(array $system): array
    {
        $history = Cache::get(self::HISTORY_CACHE_KEY, []);
        if (! is_array($history)) {
            $history = [];
        }

        $ts = now()->format('H:i:s');
        $memoryPct = isset($system['memory_usage_pct']) ? (float) $system['memory_usage_pct'] : 0.0;
        $proc = isset($system['process_count']) ? (int) $system['process_count'] : 0;

        $history[] = ['t' => $ts, 'memory_usage_pct' => $memoryPct, 'process_count' => $proc];
        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, -self::HISTORY_LIMIT);
        }

        Cache::put(self::HISTORY_CACHE_KEY, $history, now()->addHours(12));

        return [
            'labels' => array_map(fn ($row) => (string) ($row['t'] ?? ''), $history),
            'memory_usage_pct' => array_map(fn ($row) => (float) ($row['memory_usage_pct'] ?? 0), $history),
            'process_count' => array_map(fn ($row) => (int) ($row['process_count'] ?? 0), $history),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storageMetrics(): array
    {
        $target = storage_path('app/legal-vault');
        $bytes = null;
        $human = null;
        $files = 0;
        $dirs = 0;
        $error = null;

        try {
            if (! is_dir($target)) {
                $bytes = 0;
                $human = $this->formatBytes(0);
            } else {
                $bytes = 0;
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $entry) {
                    if (! $entry instanceof \SplFileInfo) {
                        continue;
                    }
                    if ($entry->isFile()) {
                        $files++;
                        $bytes += (int) $entry->getSize();
                    } elseif ($entry->isDir()) {
                        $dirs++;
                    }
                }
                $human = $this->formatBytes($bytes);
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $diskTotal = @disk_total_space($target) ?: @disk_total_space(storage_path('app'));
        $diskFree = @disk_free_space($target) ?: @disk_free_space(storage_path('app'));
        $diskUsed = null;
        $diskUsedPct = null;
        if (is_numeric($diskTotal) && is_numeric($diskFree) && (float) $diskTotal > 0) {
            $diskUsed = (int) ((float) $diskTotal - (float) $diskFree);
            $diskUsedPct = round(($diskUsed / (float) $diskTotal) * 100, 2);
        }

        return [
            'upload_documents_path' => $target,
            'upload_documents_bytes' => $bytes,
            'upload_documents_human' => $human,
            'upload_documents_files' => $files,
            'upload_documents_dirs' => $dirs,
            'upload_documents_error' => $error,
            'disk_total_bytes' => is_numeric($diskTotal) ? (int) $diskTotal : null,
            'disk_total_human' => is_numeric($diskTotal) ? $this->formatBytes((int) $diskTotal) : null,
            'disk_free_bytes' => is_numeric($diskFree) ? (int) $diskFree : null,
            'disk_free_human' => is_numeric($diskFree) ? $this->formatBytes((int) $diskFree) : null,
            'disk_used_bytes' => $diskUsed,
            'disk_used_human' => $diskUsed !== null ? $this->formatBytes($diskUsed) : null,
            'disk_used_pct' => $diskUsedPct,
        ];
    }

    private function processCount(): int
    {
        $out = [];
        $code = 0;
        @exec('ps -A -o pid= 2>/dev/null | wc -l', $out, $code);
        $val = isset($out[0]) ? trim((string) $out[0]) : '';
        if ($code !== 0 || $val === '' || ! is_numeric($val)) {
            throw new RuntimeException('Gagal membaca jumlah proses dari sistem.');
        }

        return (int) $val;
    }

    /**
     * @return array<string, mixed>
     */
    private function serverMemorySummary(): array
    {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
            return $this->linuxMemorySummary();
        }
        if (PHP_OS_FAMILY === 'Darwin') {
            return $this->darwinMemorySummary();
        }

        return [
            'php_usage_bytes' => memory_get_usage(true),
            'php_usage_human' => $this->formatBytes((int) memory_get_usage(true)),
            'php_peak_bytes' => memory_get_peak_usage(true),
            'php_peak_human' => $this->formatBytes((int) memory_get_peak_usage(true)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linuxMemorySummary(): array
    {
        $content = @file_get_contents('/proc/meminfo');
        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Gagal membaca /proc/meminfo.');
        }
        preg_match('/^MemTotal:\s+(\d+)\s+kB$/m', $content, $mTotal);
        preg_match('/^MemAvailable:\s+(\d+)\s+kB$/m', $content, $mAvail);
        $total = isset($mTotal[1]) ? ((int) $mTotal[1]) * 1024 : null;
        $avail = isset($mAvail[1]) ? ((int) $mAvail[1]) * 1024 : null;
        $used = ($total !== null && $avail !== null) ? max($total - $avail, 0) : null;

        return [
            'total_bytes' => $total,
            'total_human' => $total !== null ? $this->formatBytes($total) : null,
            'available_bytes' => $avail,
            'available_human' => $avail !== null ? $this->formatBytes($avail) : null,
            'used_bytes' => $used,
            'used_human' => $used !== null ? $this->formatBytes($used) : null,
            'php_usage_bytes' => memory_get_usage(true),
            'php_usage_human' => $this->formatBytes((int) memory_get_usage(true)),
            'php_peak_bytes' => memory_get_peak_usage(true),
            'php_peak_human' => $this->formatBytes((int) memory_get_peak_usage(true)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function darwinMemorySummary(): array
    {
        $out = [];
        $code = 0;
        @exec('vm_stat', $out, $code);
        if ($code !== 0 || $out === []) {
            throw new RuntimeException('Gagal membaca vm_stat.');
        }

        $pageSize = 4096;
        $pages = [];
        foreach ($out as $line) {
            if (preg_match('/page size of (\d+) bytes/i', $line, $m)) {
                $pageSize = (int) $m[1];

                continue;
            }
            if (preg_match('/^([^:]+):\s+([0-9\.]+)\./', trim($line), $m)) {
                $pages[trim($m[1])] = (int) $m[2];
            }
        }

        $free = (($pages['Pages free'] ?? 0) + ($pages['Pages speculative'] ?? 0)) * $pageSize;
        $active = ($pages['Pages active'] ?? 0) * $pageSize;
        $inactive = ($pages['Pages inactive'] ?? 0) * $pageSize;
        $wired = (($pages['Pages wired down'] ?? 0) + ($pages['Pages wired'] ?? 0)) * $pageSize;
        $used = $active + $inactive + $wired;
        $total = $used + $free;

        return [
            'total_bytes' => $total > 0 ? $total : null,
            'total_human' => $total > 0 ? $this->formatBytes($total) : null,
            'available_bytes' => $free > 0 ? $free : 0,
            'available_human' => $this->formatBytes(max($free, 0)),
            'used_bytes' => $used > 0 ? $used : null,
            'used_human' => $used > 0 ? $this->formatBytes($used) : null,
            'php_usage_bytes' => memory_get_usage(true),
            'php_usage_human' => $this->formatBytes((int) memory_get_usage(true)),
            'php_peak_bytes' => memory_get_peak_usage(true),
            'php_peak_human' => $this->formatBytes((int) memory_get_peak_usage(true)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function glitchtipMetrics(): array
    {
        $dsn = (string) config('sentry.dsn', '');
        $configured = $dsn !== '';

        $host = null;
        $port = 443;
        $path = null;

        if ($configured) {
            $parts = parse_url($dsn);
            $host = $parts['host'] ?? null;
            if (isset($parts['port'])) {
                $port = (int) $parts['port'];
            }
            $path = $parts['path'] ?? null;
        }

        $reachable = null;
        $reachableError = null;
        if ($host !== null) {
            try {
                $reachable = $this->tcpConnectMs($host, $port);
            } catch (Throwable $e) {
                $reachableError = $e->getMessage();
            }
        }

        $clientHasDsn = false;
        try {
            $client = SentrySdk::getCurrentHub()->getClient();
            if ($client !== null) {
                $clientDsn = (string) $client->getOptions()->getDsn();
                $clientHasDsn = $clientDsn !== '';
            }
        } catch (Throwable) {
            // Sentry SDK not yet initialised
        }

        return [
            'configured' => $configured,
            'dsn' => $configured ? $dsn : null,
            'host' => $host,
            'port' => $port,
            'project_path' => $path,
            'reachable_ms' => $reachable,
            'reachable_error' => $reachableError,
            'client_has_dsn' => $clientHasDsn,
        ];
    }
}
