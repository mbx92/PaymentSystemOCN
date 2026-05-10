<?php

namespace App\Imports;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\ProjectPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ProjectsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<array{row: int, message: string}> */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        $line = 1;

        foreach ($rows as $row) {
            $line++;
            $data = $this->normalizeRow($row);
            if ($this->isEmptyRow($data)) {
                continue;
            }

            $name = isset($data['name']) ? trim((string) $data['name']) : '';
            if ($name === '') {
                $this->errors[] = ['row' => $line, 'message' => 'Kolom name wajib diisi.'];

                continue;
            }

            $clientName = isset($data['client_name']) ? trim((string) $data['client_name']) : '';
            if ($clientName === '') {
                $this->errors[] = ['row' => $line, 'message' => "Project \"{$name}\": client_name wajib diisi."];

                continue;
            }

            $totalValue = $this->toDecimal($data['total_value'] ?? 0);
            if ((float) $totalValue < 0.01) {
                $this->errors[] = ['row' => $line, 'message' => "Project \"{$name}\": total_value harus lebih dari 0."];

                continue;
            }

            $status = strtolower(trim((string) ($data['status'] ?? 'negosiasi')));
            if (! in_array($status, ['negosiasi', 'berjalan', 'selesai', 'dibatalkan'], true)) {
                $this->errors[] = ['row' => $line, 'message' => "Project \"{$name}\": status harus negosiasi, berjalan, selesai, atau dibatalkan."];

                continue;
            }

            $projectType = strtolower(trim((string) ($data['project_type'] ?? 'system_website_development')));
            if (! in_array($projectType, ['cctv_installation', 'system_website_development'], true)) {
                $this->errors[] = ['row' => $line, 'message' => "Project \"{$name}\": project_type harus cctv_installation atau system_website_development."];

                continue;
            }

            $clientContact = isset($data['client_contact']) ? trim((string) $data['client_contact']) : '';
            if ($clientContact === '') {
                $clientContact = null;
            }

            $description = isset($data['description']) ? trim((string) $data['description']) : null;
            if ($description === '') {
                $description = null;
            }

            $invoiceNumber = isset($data['invoice_number']) ? trim((string) $data['invoice_number']) : '';
            if ($invoiceNumber === '') {
                $invoiceNumber = null;
            }

            $importKey = isset($data['import_key']) ? trim((string) $data['import_key']) : '';
            if ($importKey === '') {
                $importKey = null;
            }

            $existing = $importKey
                ? Project::query()->where('import_key', $importKey)->first()
                : null;

            $startedAt = $this->parseDate($data['started_at'] ?? null);
            $finishedAt = $this->parseDate($data['finished_at'] ?? null);

            if ($startedAt && $finishedAt && $finishedAt < $startedAt) {
                $this->errors[] = ['row' => $line, 'message' => "Project \"{$name}\": finished_at harus sama atau setelah started_at."];

                continue;
            }

            $terms = $this->parsePaymentTerms($data);
            if ($terms === null) {
                $this->errors[] = ['row' => $line, 'message' => "Project \"{$name}\": term_percentages tidak valid (pisahkan dengan koma; total harus 100%)."];

                continue;
            }

            if ($invoiceNumber) {
                $dupInvoice = Project::query()
                    ->where('invoice_number', $invoiceNumber)
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                    ->exists();
                if ($dupInvoice) {
                    $this->errors[] = ['row' => $line, 'message' => "Project \"{$name}\": invoice_number sudah dipakai project lain."];

                    continue;
                }
            }

            if ($existing && $existing->payments()->whereNotNull('paid_at')->exists()) {
                $this->errors[] = ['row' => $line, 'message' => "import_key \"{$importKey}\": ada termin yang sudah lunas; baris dilewati (ubah manual di UI)."];

                continue;
            }

            try {
                DB::transaction(function () use ($existing, $name, $clientName, $clientContact, $projectType, $totalValue, $status, $invoiceNumber, $startedAt, $finishedAt, $description, $importKey, $terms, $data): void {
                    $payload = [
                        'name' => $name,
                        'client_name' => $clientName,
                        'client_contact' => $clientContact,
                        'project_type' => $projectType,
                        'total_value' => $totalValue,
                        'status' => $status,
                        'invoice_number' => $invoiceNumber,
                        'started_at' => $startedAt,
                        'finished_at' => $finishedAt,
                        'description' => $description,
                        'import_key' => $importKey,
                    ];

                    if ($existing) {
                        $existing->update($payload);
                        $project = $existing;
                        $project->payments()->delete();
                    } else {
                        $project = Project::query()->create($payload);
                    }

                    $this->createPaymentRowsForProject($project, $terms);
                    $this->upsertProjectMaterialFromRow($project, $data);
                });
            } catch (\Throwable $e) {
                $this->errors[] = ['row' => $line, 'message' => "Project \"{$name}\": ".$e->getMessage()];

                continue;
            }

            $this->imported++;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{percentage: float, note: string|null}>|null
     */
    private function parsePaymentTerms(array $data): ?array
    {
        $raw = $data['term_percentages'] ?? $data['payment_terms'] ?? '';
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [['percentage' => 100.0, 'note' => 'Impor — pelunasan tunggal']];
        }

        $parts = preg_split('/\s*[,|]\s*/', $raw) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
        if ($parts === []) {
            return null;
        }

        $notesRaw = isset($data['term_notes']) ? trim((string) $data['term_notes']) : '';
        $noteParts = $notesRaw !== '' ? preg_split('/\s*\|\s*/', $notesRaw) : [];
        $noteParts = array_map(fn ($n) => trim((string) $n), $noteParts);

        $terms = [];
        $sum = 0.0;
        foreach ($parts as $i => $p) {
            $pct = (float) str_replace(',', '.', $p);
            if ($pct <= 0 || $pct > 100) {
                return null;
            }
            $sum += $pct;
            $note = $noteParts[$i] ?? null;
            if ($note === '') {
                $note = null;
            }
            $terms[] = ['percentage' => $pct, 'note' => $note];
        }

        if (abs($sum - 100) > 0.02) {
            return null;
        }

        return $terms;
    }

    private function createPaymentRowsForProject(Project $project, array $payments): void
    {
        $totalValue = (float) $project->total_value;
        $n = count($payments);
        $assigned = 0.0;

        foreach ($payments as $i => $term) {
            $pct = (float) $term['percentage'];
            if ($i === $n - 1) {
                $amount = round($totalValue - $assigned, 2);
            } else {
                $amount = round($totalValue * ($pct / 100), 2);
                $assigned += $amount;
            }

            ProjectPayment::create([
                'project_id' => $project->id,
                'term_number' => $i + 1,
                'percentage' => $pct,
                'amount' => $amount,
                'note' => $term['note'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertProjectMaterialFromRow(Project $project, array $data): void
    {
        $sku = trim((string) ($data['item_sku'] ?? $data['material_sku'] ?? ''));
        $warehouseCode = trim((string) ($data['item_warehouse_code'] ?? $data['material_warehouse_code'] ?? ''));
        $plannedRaw = $data['item_planned_qty'] ?? $data['material_planned_qty'] ?? null;
        $notes = trim((string) ($data['item_notes'] ?? $data['material_notes'] ?? ''));
        $status = strtolower(trim((string) ($data['item_status'] ?? $data['material_status'] ?? 'reserved')));

        $reservedRaw = $data['item_reserved_qty'] ?? $data['material_reserved_qty'] ?? null;
        $issuedRaw = $data['item_issued_qty'] ?? $data['material_issued_qty'] ?? null;

        $hasMaterialPayload = $sku !== ''
            || $warehouseCode !== ''
            || ($plannedRaw !== null && $plannedRaw !== '')
            || ($reservedRaw !== null && $reservedRaw !== '')
            || ($issuedRaw !== null && $issuedRaw !== '')
            || $notes !== '';

        if (! $hasMaterialPayload) {
            return;
        }

        if ($sku === '') {
            throw new \RuntimeException('item_sku wajib diisi jika ingin import item/material project.');
        }

        $product = MasterProduct::query()
            ->where('sku', $sku)
            ->where('product_type', 'project_material')
            ->first();
        if (! $product) {
            throw new \RuntimeException("item_sku \"{$sku}\" tidak ditemukan atau bukan project_material.");
        }

        $plannedQty = (float) $this->toDecimal($plannedRaw);
        if ($plannedQty <= 0) {
            throw new \RuntimeException("Item {$sku}: item_planned_qty harus lebih dari 0.");
        }

        $reservedQty = (float) $this->toDecimal($reservedRaw);
        $issuedQty = (float) $this->toDecimal($issuedRaw);
        if ($reservedQty < 0 || $issuedQty < 0) {
            throw new \RuntimeException("Item {$sku}: item_reserved_qty / item_issued_qty tidak boleh negatif.");
        }
        if ($reservedQty > $plannedQty) {
            throw new \RuntimeException("Item {$sku}: item_reserved_qty tidak boleh melebihi item_planned_qty.");
        }
        if ($issuedQty > $reservedQty) {
            throw new \RuntimeException("Item {$sku}: item_issued_qty tidak boleh melebihi item_reserved_qty.");
        }

        if ($status === '') {
            $status = 'reserved';
        }

        $warehouse = $warehouseCode !== ''
            ? Warehouse::query()->where('code', $warehouseCode)->first()
            : Warehouse::query()->where('is_active', true)->orderBy('name')->first();

        if (! $warehouse) {
            throw new \RuntimeException(
                $warehouseCode !== ''
                    ? "Warehouse dengan kode \"{$warehouseCode}\" tidak ditemukan."
                    : 'Warehouse aktif tidak ditemukan untuk item project.'
            );
        }

        $material = ProjectMaterial::query()->where([
            'project_id' => $project->id,
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
        ])->lockForUpdate()->first();

        $oldReserved = (float) ($material?->reserved_qty ?? 0);
        $deltaReserve = $reservedQty - $oldReserved;

        $stock = MasterProductWarehouseStock::query()
            ->lockForUpdate()
            ->firstOrCreate(
                [
                    'master_product_id' => (int) $product->id,
                    'warehouse_id' => (int) $warehouse->id,
                ],
                ['qty' => 0, 'reserved_qty' => 0]
            );

        $stockQty = (float) $stock->qty;
        $stockReserved = (float) $stock->reserved_qty;

        if ($deltaReserve > 0) {
            $available = $stockQty - $stockReserved;
            if ($deltaReserve > $available) {
                throw new \RuntimeException(
                    "Item {$sku}: stok tersedia di gudang {$warehouse->code} tidak cukup untuk reserve tambahan {$deltaReserve} (available {$available})."
                );
            }
            $stock->reserved_qty = number_format($stockReserved + $deltaReserve, 2, '.', '');
            $stock->save();
        } elseif ($deltaReserve < 0) {
            $newReserved = max($stockReserved + $deltaReserve, 0);
            $stock->reserved_qty = number_format($newReserved, 2, '.', '');
            $stock->save();
        }

        if ($material) {
            $material->update([
                'planned_qty' => number_format($plannedQty, 2, '.', ''),
                'reserved_qty' => number_format($reservedQty, 2, '.', ''),
                'issued_qty' => number_format($issuedQty, 2, '.', ''),
                'status' => substr($status, 0, 20),
                'notes' => $notes !== '' ? $notes : null,
            ]);

            return;
        }

        ProjectMaterial::query()->create([
            'project_id' => $project->id,
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'planned_qty' => number_format($plannedQty, 2, '.', ''),
            'reserved_qty' => number_format($reservedQty, 2, '.', ''),
            'issued_qty' => number_format($issuedQty, 2, '.', ''),
            'status' => substr($status, 0, 20),
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }

    /**
     * @param  Collection<int, mixed>|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(Collection|array $row): array
    {
        $arr = is_array($row) ? $row : $row->toArray();
        $out = [];
        foreach ($arr as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            $k = Str::slug((string) $key, '_');
            $out[$k] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        foreach ($data as $v) {
            if ($v !== null && $v !== '') {
                return false;
            }
        }

        return true;
    }

    private function toDecimal(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '0.00';
        }
        if (is_numeric($v)) {
            return number_format((float) $v, 2, '.', '');
        }
        $s = str_replace([','], ['.'], preg_replace('/[^\d.,\-]/', '', (string) $v));

        return number_format((float) $s, 2, '.', '');
    }

    private function parseDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_numeric($v)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
