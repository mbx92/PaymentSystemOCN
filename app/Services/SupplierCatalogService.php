<?php

namespace App\Services;

use App\Models\SupplierCatalogItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SupplierCatalogService
{
    /**
     * @return list<array{key: string, label: string, sheet_name: string}>
     */
    public function sheets(): array
    {
        return array_values(array_map(
            fn (array $sheet): array => [
                'key' => (string) $sheet['key'],
                'label' => (string) $sheet['label'],
                'sheet_name' => (string) $sheet['sheet_name'],
            ],
            config('supplier_catalog.sheets', []),
        ));
    }

    public function supplierName(): string
    {
        return (string) config('supplier_catalog.supplier_name', 'Supplier');
    }

    /**
     * @return list<array{
     *     ref: string,
     *     code: string,
     *     name: string,
     *     category: string,
     *     supplier_price: float,
     *     last_price: float|null,
     *     last_synced_at: string|null,
     *     sheet_key: string,
     *     sheet_label: string,
     *     supplier_name: string
     * }>
     */
    public function itemsForSheet(string $sheetKey, ?string $search = null): array
    {
        if (! $this->findSheet($sheetKey)) {
            return [];
        }

        $query = SupplierCatalogItem::query()
            ->where('sheet_key', $sheetKey)
            ->orderBy('code');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('category', 'like', $term);
            });
        }

        return $query
            ->get()
            ->map(fn (SupplierCatalogItem $item): array => $this->mapStoredItem($item))
            ->values()
            ->all();
    }

    public function lastSyncedAt(): ?Carbon
    {
        $value = SupplierCatalogItem::query()->max('last_synced_at');

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * @return list<array{
     *     ref: string,
     *     code: string,
     *     name: string,
     *     category: string,
     *     supplier_price: float,
     *     sheet_key: string,
     *     sheet_label: string,
     *     supplier_name: string
     * }>
     */
    public function fetchRemoteItemsForSheet(string $sheetKey): array
    {
        $sheet = $this->findSheet($sheetKey);
        if (! $sheet) {
            throw new RuntimeException("Tab katalog \"{$sheetKey}\" tidak dikenali.");
        }

        return $this->fetchAndParseSheet($sheet);
    }

    /**
     * @return array{
     *     ref: string,
     *     code: string,
     *     name: string,
     *     category: string,
     *     supplier_price: float,
     *     last_price: float|null,
     *     last_synced_at: string|null,
     *     sheet_key: string,
     *     sheet_label: string,
     *     supplier_name: string
     * }
     */
    public function mapStoredItem(SupplierCatalogItem $item): array
    {
        return [
            'ref' => $item->ref,
            'code' => $item->code,
            'name' => $item->name,
            'category' => (string) ($item->category ?? ''),
            'supplier_price' => (float) $item->supplier_price,
            'last_price' => $item->last_price !== null ? (float) $item->last_price : null,
            'last_synced_at' => $item->last_synced_at?->toIso8601String(),
            'sheet_key' => $item->sheet_key,
            'sheet_label' => $item->sheet_label,
            'supplier_name' => $item->supplier_name,
        ];
    }

    /**
     * @return array{key: string, label: string, sheet_name: string}|null
     */
    public function findSheet(string $sheetKey): ?array
    {
        foreach (config('supplier_catalog.sheets', []) as $sheet) {
            if (($sheet['key'] ?? '') === $sheetKey) {
                return [
                    'key' => (string) $sheet['key'],
                    'label' => (string) $sheet['label'],
                    'sheet_name' => (string) $sheet['sheet_name'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  array{key: string, label: string, sheet_name: string}  $sheet
     * @return list<array{
     *     ref: string,
     *     code: string,
     *     name: string,
     *     category: string,
     *     supplier_price: float,
     *     sheet_key: string,
     *     sheet_label: string,
     *     supplier_name: string
     * }>
     */
    private function fetchAndParseSheet(array $sheet): array
    {
        $csv = $this->fetchSheetCsv($sheet['sheet_name']);

        return $this->parseCsv($csv, $sheet);
    }

    private function fetchSheetCsv(string $sheetName): string
    {
        $spreadsheetId = (string) config('supplier_catalog.spreadsheet_id');
        $url = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&sheet=%s',
            $spreadsheetId,
            rawurlencode($sheetName),
        );

        $response = Http::timeout(30)->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("Gagal memuat tab \"{$sheetName}\" dari Google Sheets.");
        }

        $body = trim($response->body());
        if ($body === '') {
            throw new RuntimeException("Tab \"{$sheetName}\" kosong atau tidak dapat diakses.");
        }

        return $body;
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @param  array{key: string, label: string, sheet_name?: string, gid?: string}  $sheet
     * @return list<array{
     *     ref: string,
     *     code: string,
     *     name: string,
     *     category: string,
     *     supplier_price: float,
     *     sheet_key: string,
     *     sheet_label: string,
     *     supplier_name: string
     * }>
     */
    public function parseSheetRows(array $rows, array $sheet): array
    {
        $headerIndex = null;
        $columnMap = [];

        foreach ($rows as $index => $row) {
            $normalized = array_map(fn ($cell) => Str::lower(trim((string) $cell)), $row);

            if (! $this->rowHasCatalogHeader($normalized)) {
                continue;
            }

            $headerIndex = $index;
            foreach ($normalized as $colIndex => $header) {
                if ($header === '' || $header === '0') {
                    continue;
                }

                if (Str::contains($header, 'kode item') || in_array($header, ['kode_item', 'kode'], true)) {
                    $columnMap['code'] = $colIndex;
                } elseif (Str::contains($header, 'nama item')) {
                    $columnMap['name'] = $colIndex;
                } elseif ($header === 'jenis') {
                    $columnMap['category'] = $colIndex;
                } elseif ($header === 'harga') {
                    $columnMap['price'] = $colIndex;
                }
            }
            break;
        }

        if ($headerIndex === null) {
            return [];
        }

        $items = [];
        $supplierName = $this->supplierName();
        $sheetKey = (string) $sheet['key'];
        $sheetLabel = (string) $sheet['label'];
        $codeIdx = $columnMap['code'] ?? 0;

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $parsed = $this->parseDataRow($row, $columnMap, $codeIdx);
            if ($parsed === null) {
                continue;
            }

            [$code, $name, $category, $supplierPrice] = $parsed;

            if ($code === '') {
                $code = Str::upper(Str::slug($name, '-'));
            }

            $items[] = [
                'ref' => $sheetKey.':'.$code,
                'code' => $code,
                'name' => $name,
                'category' => $category !== '' ? $category : $sheetLabel,
                'supplier_price' => $supplierPrice,
                'sheet_key' => $sheetKey,
                'sheet_label' => $sheetLabel,
                'supplier_name' => $supplierName,
            ];
        }

        return $items;
    }

    /**
     * @param  list<string>  $normalizedRow
     */
    private function rowHasCatalogHeader(array $normalizedRow): bool
    {
        $hasName = false;
        $hasCodeOrPrice = false;

        foreach ($normalizedRow as $header) {
            if (Str::contains($header, 'nama item')) {
                $hasName = true;
            }
            if (Str::contains($header, 'kode item') || $header === 'harga') {
                $hasCodeOrPrice = true;
            }
        }

        return $hasName && $hasCodeOrPrice;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $columnMap
     * @return array{0: string, 1: string, 2: string, 3: float}|null
     */
    private function parseDataRow(array $row, array $columnMap, int $codeIdx): ?array
    {
        $code = trim((string) ($row[$codeIdx] ?? ''));
        if ($code === '' || Str::contains(Str::lower($code), 'kode item')) {
            return null;
        }

        $nameIdx = $columnMap['name'] ?? ($codeIdx + 1);
        $headerNameGap = $nameIdx - $codeIdx;

        if ($headerNameGap > 1 && isset($row[$codeIdx + 1]) && ! $this->looksLikePrice($row[$codeIdx + 1])) {
            $nameIdx = $codeIdx + 1;
        }

        $name = trim((string) ($row[$nameIdx] ?? ''));
        if ($name === '' && isset($row[$codeIdx + 1])) {
            $name = trim((string) $row[$codeIdx + 1]);
        }
        if ($name === '') {
            return null;
        }

        $category = '';
        if (isset($columnMap['category'])) {
            $category = trim((string) ($row[$columnMap['category']] ?? ''));
        }

        $priceIdx = $columnMap['price'] ?? null;
        if ($priceIdx === null) {
            for ($j = count($row) - 1; $j > $codeIdx; $j--) {
                if ($this->looksLikePrice($row[$j] ?? null)) {
                    $priceIdx = $j;
                    break;
                }
            }
        }

        if ($category === '' && $priceIdx !== null) {
            for ($j = $codeIdx + 1; $j < $priceIdx; $j++) {
                if ($j === $nameIdx || $j === ($columnMap['name'] ?? -1)) {
                    continue;
                }
                $candidate = trim((string) ($row[$j] ?? ''));
                if ($candidate !== '' && ! $this->looksLikePrice($candidate)) {
                    $category = $candidate;
                    break;
                }
            }
        }

        $priceRaw = $priceIdx !== null ? ($row[$priceIdx] ?? 0) : 0;
        $supplierPrice = is_numeric($priceRaw)
            ? (float) $priceRaw
            : $this->parseRupiah((string) $priceRaw);

        return [$code, $name, $category, $supplierPrice];
    }

    /**
     * @param  array{key: string, label: string, sheet_name?: string, gid?: string}  $sheet
     * @return list<array<string, mixed>>
     */
    public function parseCsv(string $csv, array $sheet): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $rows = array_map(str_getcsv(...), $lines);

        return $this->parseSheetRows($rows, $sheet);
    }

    private function looksLikePrice(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_numeric($value)) {
            return true;
        }

        $text = Str::lower(trim((string) $value));

        return Str::startsWith($text, 'rp') || preg_match('/^[\d,.-]+$/', $text) === 1;
    }

    private function parseRupiah(string $value): float
    {
        $value = trim($value);
        if ($value === '' || Str::lower($value) === 'rp0') {
            return 0.0;
        }

        $numeric = preg_replace('/[^\d,.-]/', '', $value) ?? '';
        $numeric = str_replace(',', '', $numeric);

        return (float) $numeric;
    }
}
