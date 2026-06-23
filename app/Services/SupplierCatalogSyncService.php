<?php

namespace App\Services;

use App\Models\SupplierCatalogItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SupplierCatalogSyncService
{
    public function __construct(
        private readonly SupplierCatalogService $catalog,
    ) {}

    /**
     * @return array{sheets: int, created: int, updated: int, removed: int, failed: list<string>}
     */
    public function syncAll(?callable $onSheet = null): array
    {
        $summary = [
            'sheets' => 0,
            'created' => 0,
            'updated' => 0,
            'removed' => 0,
            'failed' => [],
        ];

        foreach ($this->catalog->sheets() as $sheet) {
            $sheetKey = (string) $sheet['key'];
            try {
                $result = $this->syncSheet($sheetKey);
                $summary['sheets']++;
                $summary['created'] += $result['created'];
                $summary['updated'] += $result['updated'];
                $summary['removed'] += $result['removed'];
            } catch (RuntimeException $exception) {
                $summary['failed'][] = $sheetKey.': '.$exception->getMessage();
            }

            if ($onSheet) {
                $onSheet($sheetKey, $summary);
            }
        }

        return $summary;
    }

    /**
     * @return array{created: int, updated: int, removed: int}
     */
    public function syncSheet(string $sheetKey): array
    {
        $remoteItems = $this->catalog->fetchRemoteItemsForSheet($sheetKey);
        $syncedAt = now();

        return DB::transaction(function () use ($remoteItems, $sheetKey, $syncedAt): array {
            $created = 0;
            $updated = 0;
            $refs = [];

            foreach ($remoteItems as $item) {
                $refs[] = $item['ref'];
                $existing = SupplierCatalogItem::query()->where('ref', $item['ref'])->first();

                if ($existing) {
                    $this->applyRemoteItem($existing, $item, $syncedAt);
                    $existing->save();
                    $updated++;

                    continue;
                }

                SupplierCatalogItem::query()->create([
                    'ref' => $item['ref'],
                    'sheet_key' => $item['sheet_key'],
                    'sheet_label' => $item['sheet_label'],
                    'supplier_name' => $item['supplier_name'],
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'supplier_price' => $item['supplier_price'],
                    'last_price' => null,
                    'last_synced_at' => $syncedAt,
                ]);
                $created++;
            }

            $removed = SupplierCatalogItem::query()
                ->where('sheet_key', $sheetKey)
                ->when($refs !== [], fn ($query) => $query->whereNotIn('ref', $refs))
                ->when($refs === [], fn ($query) => $query)
                ->delete();

            return [
                'created' => $created,
                'updated' => $updated,
                'removed' => $removed,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyRemoteItem(SupplierCatalogItem $existing, array $item, Carbon $syncedAt): void
    {
        $newPrice = (float) $item['supplier_price'];
        $currentPrice = (float) $existing->supplier_price;

        if (round($newPrice, 2) !== round($currentPrice, 2)) {
            $existing->last_price = $currentPrice;
        }

        $existing->fill([
            'sheet_key' => $item['sheet_key'],
            'sheet_label' => $item['sheet_label'],
            'supplier_name' => $item['supplier_name'],
            'code' => $item['code'],
            'name' => $item['name'],
            'category' => $item['category'],
            'supplier_price' => $newPrice,
            'last_synced_at' => $syncedAt,
        ]);
    }
}
