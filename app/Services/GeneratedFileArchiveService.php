<?php

namespace App\Services;

use App\Models\ErpSetting;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GeneratedFileArchiveService
{
    public const CATEGORY_PDF = 'pdf';

    public const CATEGORY_EXCEL = 'excel';

    public const CATEGORY_DATABASE = 'database';

    private const DISK_NAME = 'erp_archive';

    public function settings(): ?ErpSetting
    {
        return ErpSetting::query()->first();
    }

    public function isEnabledFor(string $category): bool
    {
        $setting = $this->settings();
        if (! $setting?->object_storage_enabled || ! $this->hasRequiredCredentials($setting)) {
            return false;
        }

        return match ($category) {
            self::CATEGORY_PDF => (bool) $setting->object_storage_archive_pdf,
            self::CATEGORY_EXCEL => (bool) $setting->object_storage_archive_excel,
            self::CATEGORY_DATABASE => (bool) $setting->object_storage_archive_database,
            default => false,
        };
    }

    public function disk(?ErpSetting $setting = null): Filesystem
    {
        $setting ??= $this->settings();
        if (! $setting || ! $this->hasRequiredCredentials($setting)) {
            throw new RuntimeException('Object storage belum dikonfigurasi.');
        }

        config(['filesystems.disks.'.self::DISK_NAME => $this->diskConfig($setting)]);

        return Storage::disk(self::DISK_NAME);
    }

    /**
     * @return array<string, mixed>
     */
    public function diskConfig(ErpSetting $setting): array
    {
        $endpoint = $this->normalizeEndpoint($setting->object_storage_endpoint);

        return [
            'driver' => 's3',
            'key' => $setting->object_storage_access_key,
            'secret' => $setting->resolvedObjectStorageSecretKey(),
            'region' => $setting->object_storage_region ?: 'us-east-1',
            'bucket' => $setting->object_storage_bucket,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => $this->shouldUsePathStyleEndpoint($setting, $endpoint),
            'throw' => true,
        ];
    }

    public function archiveContent(string $content, string $category, string $filename, ?string $contentType = null): ?string
    {
        if (! $this->isEnabledFor($category)) {
            return null;
        }

        try {
            $path = $this->buildObjectPath($category, $filename);
            $options = $contentType !== null ? ['ContentType' => $contentType] : [];
            $this->disk()->put($path, $content, $options);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Gagal meng-archive file ke object storage.', [
                'category' => $category,
                'filename' => $filename,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function archiveLocalFile(string $localPath, string $category, string $filename, ?string $contentType = null): ?string
    {
        if (! is_file($localPath)) {
            return null;
        }

        return $this->archiveContent((string) file_get_contents($localPath), $category, $filename, $contentType);
    }

    public function downloadPdf(DompdfWrapper $pdf, string $filename, string $category = self::CATEGORY_PDF): Response
    {
        $binary = $pdf->output();
        $this->archiveContent($binary, $category, $filename, 'application/pdf');

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->escapeFilename($filename).'"',
        ]);
    }

    /**
     * @param  mixed  $export
     */
    public function downloadExcel($export, string $filename, string $category = self::CATEGORY_EXCEL): BinaryFileResponse
    {
        $binary = Excel::raw($export, ExcelFormat::XLSX);
        $this->archiveContent(
            $binary,
            $category,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        return Excel::download($export, $filename);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{success: bool, message: string}
     */
    public function testConnection(array $input = []): array
    {
        $setting = $this->settings() ?? new ErpSetting([
            'object_storage_region' => 'us-east-1',
            'object_storage_prefix' => 'erp-archive',
        ]);

        $candidate = new ErpSetting([
            'object_storage_access_key' => trim((string) ($input['object_storage_access_key'] ?? $setting->object_storage_access_key ?? '')),
            'object_storage_bucket' => trim((string) ($input['object_storage_bucket'] ?? $setting->object_storage_bucket ?? '')),
            'object_storage_region' => trim((string) ($input['object_storage_region'] ?? $setting->object_storage_region ?? 'us-east-1')),
            'object_storage_endpoint' => trim((string) ($input['object_storage_endpoint'] ?? $setting->object_storage_endpoint ?? '')) ?: null,
            'object_storage_use_path_style' => filter_var(
                $input['object_storage_use_path_style'] ?? $setting->object_storage_use_path_style ?? false,
                FILTER_VALIDATE_BOOL,
            ),
        ]);

        $secret = trim((string) ($input['object_storage_secret_key'] ?? ''));
        if ($secret !== '') {
            $candidate->object_storage_secret_key = $secret;
        } elseif (filled($setting->object_storage_secret_key)) {
            $candidate->object_storage_secret_key = $setting->object_storage_secret_key;
        }

        if (! $this->hasRequiredCredentials($candidate)) {
            return [
                'success' => false,
                'message' => 'Lengkapi access key, secret key, dan bucket sebelum uji koneksi.',
            ];
        }

        try {
            $disk = (function () use ($candidate): Filesystem {
                config(['filesystems.disks.'.self::DISK_NAME => $this->diskConfig($candidate)]);

                return Storage::disk(self::DISK_NAME);
            })();
            $testPath = trim((string) ($input['object_storage_prefix'] ?? $setting->object_storage_prefix ?? 'erp-archive'), '/')
                .'/__connection-test/'.Str::uuid()->toString().'.txt';

            $disk->put($testPath, 'ocn-erp-connection-test '.now()->toIso8601String());
            $disk->delete($testPath);

            return [
                'success' => true,
                'message' => 'Koneksi ke bucket berhasil. File uji coba berhasil ditulis dan dihapus.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Koneksi gagal: '.$e->getMessage(),
            ];
        }
    }

    private function buildObjectPath(string $category, string $filename): string
    {
        $prefix = trim((string) ($this->settings()?->object_storage_prefix ?? 'erp-archive'), '/');
        $basename = basename($filename);
        $safeName = Str::slug(pathinfo($basename, PATHINFO_FILENAME), '-');
        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        $name = $extension !== '' ? $safeName.'.'.$extension : $safeName;

        if ($name === '' || $name === '.') {
            $name = 'file-'.now()->format('Ymd-His');
        }

        return $prefix.'/'.$category.'/'.now()->format('Y/m/d').'/'.$name;
    }

    private function hasRequiredCredentials(ErpSetting $setting): bool
    {
        return filled($setting->object_storage_access_key)
            && filled($setting->resolvedObjectStorageSecretKey())
            && filled($setting->object_storage_bucket);
    }

    private function normalizeEndpoint(?string $endpoint): ?string
    {
        $value = trim((string) $endpoint);

        if ($value === '') {
            return null;
        }

        return rtrim($value, '/');
    }

    private function shouldUsePathStyleEndpoint(ErpSetting $setting, ?string $endpoint): bool
    {
        if ((bool) $setting->object_storage_use_path_style) {
            return true;
        }

        if (! $endpoint) {
            return false;
        }

        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        return ! str_contains($host, 'amazonaws.com');
    }

    private function escapeFilename(string $filename): string
    {
        return str_replace('"', '', $filename);
    }
}
