<?php

namespace App\Services;

use App\ERP\Purchasing\Models\Vendor;
use App\ERP\Shared\Services\DocumentNumberService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacySupplierImportService
{
    private const CONNECTION = 'legacy_ocn';

    private const NOTE_IMPORT_SOURCE = 'Imported from legacy OCN supplier.';

    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildReport(): array
    {
        $connection = DB::connection(self::CONNECTION);
        $config = (array) config('database.connections.'.self::CONNECTION, []);

        $hasUrl = trim((string) ($config['url'] ?? '')) !== '';
        $hasDatabase = trim((string) ($config['database'] ?? '')) !== '';

        if (! $hasUrl && ! $hasDatabase) {
            throw new RuntimeException('Koneksi legacy belum dikonfigurasi. Isi LEGACY_DB_* pada environment ERP terlebih dahulu.');
        }

        try {
            $connection->getPdo();
        } catch (\Throwable $e) {
            throw new RuntimeException('Gagal terhubung ke database legacy: '.$e->getMessage(), previous: $e);
        }

        $legacySuppliers = collect($connection->select(<<<'SQL'
            SELECT
                id,
                name,
                "contactPerson" AS contact_person,
                phone,
                email,
                address,
                "createdAt" AS created_at,
                "updatedAt" AS updated_at
            FROM "Supplier"
            ORDER BY name, id
        SQL));

        $rows = [];
        $alreadyImportedCount = 0;
        $matchedIdentityCount = 0;
        $pendingImportCount = 0;

        foreach ($legacySuppliers as $supplier) {
            $matchedVendor = $this->matchExistingVendor($supplier);
            $status = $this->resolveImportStatus($supplier, $matchedVendor);

            if ($status['key'] === 'already_imported') {
                $alreadyImportedCount++;
            } elseif ($status['key'] === 'matched_existing_vendor') {
                $matchedIdentityCount++;
            } else {
                $pendingImportCount++;
            }

            $rows[] = [
                'legacy_id' => (string) $supplier->id,
                'name' => trim((string) ($supplier->name ?? '')),
                'contact_person' => trim((string) ($supplier->contact_person ?? '')),
                'phone' => trim((string) ($supplier->phone ?? '')),
                'email' => trim((string) ($supplier->email ?? '')),
                'address' => trim((string) ($supplier->address ?? '')),
                'created_at' => $supplier->created_at,
                'updated_at' => $supplier->updated_at,
                'import_status' => $status,
                'is_importable' => $status['key'] !== 'already_imported',
                'matched_vendor' => $matchedVendor ? [
                    'id' => $matchedVendor->id,
                    'code' => $matchedVendor->code,
                    'name' => $matchedVendor->name,
                ] : null,
            ];
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'source' => [
                'connection' => self::CONNECTION,
                'host' => (string) ($config['host'] ?? ''),
                'database' => (string) ($config['database'] ?? ''),
                'schema' => (string) ($config['search_path'] ?? 'public'),
                'table' => 'Supplier',
            ],
            'summary' => [
                'total_suppliers' => count($rows),
                'already_imported' => $alreadyImportedCount,
                'matched_existing_vendor' => $matchedIdentityCount,
                'pending_import' => $pendingImportCount,
            ],
            'suppliers' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function importAll(int $performedByUserId): array
    {
        $report = $this->buildReport();
        $suppliers = collect($report['suppliers'] ?? []);

        return $this->importCollection($suppliers, $performedByUserId);
    }

    /**
     * @param  list<string>  $legacyIds
     * @return array<string, mixed>
     */
    public function importSelected(array $legacyIds, int $performedByUserId): array
    {
        $legacyIds = array_values(array_unique(array_filter(array_map('strval', $legacyIds))));

        if ($legacyIds === []) {
            throw new RuntimeException('Tidak ada supplier yang dipilih untuk diimport.');
        }

        $report = $this->buildReport();
        $suppliers = collect($report['suppliers'] ?? [])
            ->filter(fn (array $supplier) => in_array((string) ($supplier['legacy_id'] ?? ''), $legacyIds, true))
            ->values();

        if ($suppliers->isEmpty()) {
            throw new RuntimeException('Supplier yang dipilih tidak ditemukan di preview legacy terbaru.');
        }

        return $this->importCollection($suppliers, $performedByUserId);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $suppliers
     * @return array<string, mixed>
     */
    private function importCollection($suppliers, int $performedByUserId): array
    {

        $created = [];
        $updated = [];
        $skipped = [];

        foreach ($suppliers as $supplier) {
            $name = trim((string) ($supplier['name'] ?? ''));

            if ($name === '') {
                $skipped[] = 'Legacy supplier '.$supplier['legacy_id'].' dilewati karena nama kosong.';

                continue;
            }

            if (($supplier['import_status']['key'] ?? null) === 'already_imported') {
                $skipped[] = 'Legacy supplier '.$supplier['legacy_id'].' - '.$name.' dilewati karena sudah terhubung ke vendor ERP.';

                continue;
            }

            DB::transaction(function () use ($supplier, &$created, &$updated): void {
                $legacyId = (string) $supplier['legacy_id'];
                $legacyObject = (object) $supplier;
                $existingVendor = $this->matchExistingVendor($legacyObject);
                $notes = $this->mergedNotes($supplier, $existingVendor?->notes);

                $payload = [
                    'name' => $this->resolvedFieldValue((string) ($supplier['name'] ?? ''), $existingVendor?->name),
                    'email' => $this->resolvedNullableFieldValue((string) ($supplier['email'] ?? ''), $existingVendor?->email),
                    'phone' => $this->resolvedNullableFieldValue((string) ($supplier['phone'] ?? ''), $existingVendor?->phone),
                    'address' => $this->resolvedNullableFieldValue((string) ($supplier['address'] ?? ''), $existingVendor?->address),
                    'lead_time_days' => (int) ($existingVendor?->lead_time_days ?: 7),
                    'payment_terms' => $existingVendor?->payment_terms,
                    'tax_id' => $existingVendor?->tax_id,
                    'is_active' => true,
                    'notes' => $notes,
                ];

                if ($existingVendor) {
                    $existingVendor->update($payload);
                    $updated[] = $existingVendor->code.' - '.$existingVendor->name.' [legacy '.$legacyId.']';

                    return;
                }

                $vendor = Vendor::query()->create([
                    ...$payload,
                    'code' => $this->documentNumberService->next('purchasing', 'supplier_code', [
                        'prefix' => 'SUP',
                        'padding_length' => 3,
                    ]),
                ]);

                $created[] = $vendor->code.' - '.$vendor->name.' [legacy '.$legacyId.']';
            });
        }

        return [
            'created_count' => count($created),
            'updated_count' => count($updated),
            'skipped_count' => count($skipped),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private function matchExistingVendor(object $legacySupplier): ?Vendor
    {
        $legacyId = (string) ($legacySupplier->id ?? '');
        if ($legacyId !== '') {
            $byLegacyId = Vendor::query()
                ->where('notes', 'like', '%Legacy Supplier ID: '.$legacyId.'%')
                ->first();

            if ($byLegacyId) {
                return $byLegacyId;
            }
        }

        $email = mb_strtolower(trim((string) ($legacySupplier->email ?? '')));
        if ($email !== '') {
            $byEmail = Vendor::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        $phone = $this->normalizePhone((string) ($legacySupplier->phone ?? ''));
        if ($phone !== '') {
            $byPhone = Vendor::query()
                ->get(['id', 'code', 'name', 'phone', 'notes', 'email', 'address', 'lead_time_days', 'payment_terms', 'tax_id'])
                ->first(fn (Vendor $vendor) => $this->normalizePhone((string) $vendor->phone) === $phone);

            if ($byPhone) {
                return $byPhone;
            }
        }

        $name = $this->normalizeName((string) ($legacySupplier->name ?? ''));
        if ($name === '') {
            return null;
        }

        return Vendor::query()
            ->get(['id', 'code', 'name', 'phone', 'notes', 'email', 'address', 'lead_time_days', 'payment_terms', 'tax_id'])
            ->first(fn (Vendor $vendor) => $this->normalizeName((string) $vendor->name) === $name);
    }

    /**
     * @return array{key: string, label: string, badge: string, description: string}
     */
    private function resolveImportStatus(object $legacySupplier, ?Vendor $matchedVendor): array
    {
        if (! $matchedVendor) {
            return [
                'key' => 'pending_import',
                'label' => 'Belum diimport',
                'badge' => 'badge-ghost',
                'description' => 'Belum ada vendor ERP yang terhubung ke supplier legacy ini.',
            ];
        }

        $legacyId = (string) ($legacySupplier->id ?? '');
        $notes = (string) ($matchedVendor->notes ?? '');

        if ($legacyId !== '' && str_contains($notes, 'Legacy Supplier ID: '.$legacyId)) {
            return [
                'key' => 'already_imported',
                'label' => 'Sudah terhubung',
                'badge' => 'badge-success',
                'description' => 'Supplier legacy ini sudah pernah diimport ke vendor ERP.',
            ];
        }

        return [
            'key' => 'matched_existing_vendor',
            'label' => 'Match vendor ERP',
            'badge' => 'badge-warning',
            'description' => 'Ada vendor ERP yang cocok. Import akan update dan menandai vendor itu.',
        ];
    }

    /**
     * @param  array<string, mixed>  $supplier
     */
    private function mergedNotes(array $supplier, ?string $existingNotes): string
    {
        $notes = collect([
            $existingNotes,
            self::NOTE_IMPORT_SOURCE,
            'Legacy Supplier ID: '.($supplier['legacy_id'] ?? '-'),
            trim((string) ($supplier['contact_person'] ?? '')) !== '' ? 'Legacy Contact Person: '.trim((string) $supplier['contact_person']) : null,
        ])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        return $notes->implode("\n");
    }

    private function resolvedFieldValue(string $legacyValue, ?string $existingValue): string
    {
        $legacyValue = trim($legacyValue);
        $existingValue = trim((string) $existingValue);

        return $legacyValue !== '' ? $legacyValue : $existingValue;
    }

    private function resolvedNullableFieldValue(string $legacyValue, ?string $existingValue): ?string
    {
        $resolved = $this->resolvedFieldValue($legacyValue, $existingValue);

        return $resolved !== '' ? $resolved : null;
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
