<?php

namespace App\Http\Controllers;

use App\Services\SupplierCatalogService;
use App\Services\SupplierCatalogSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierCatalogController extends Controller
{
    public function __construct(
        private readonly SupplierCatalogService $catalog,
    ) {}

    public function page(): Response
    {
        return Inertia::render('ERP/Projects/SupplierCatalog', [
            'supplier_name' => $this->catalog->supplierName(),
            'sheets' => $this->catalog->sheets(),
            'last_synced_at' => $this->catalog->lastSyncedAt()?->toIso8601String(),
            'sync_schedule' => (string) config('supplier_catalog.sync_time', '02:00'),
        ]);
    }

    public function sheets(): JsonResponse
    {
        return response()->json([
            'supplier_name' => $this->catalog->supplierName(),
            'sheets' => $this->catalog->sheets(),
            'last_synced_at' => $this->catalog->lastSyncedAt()?->toIso8601String(),
        ]);
    }

    public function items(Request $request, string $sheetKey): JsonResponse
    {
        $search = $request->string('q')->toString();

        $items = $this->catalog->itemsForSheet($sheetKey, $search !== '' ? $search : null);
        $source = 'database';

        if ($items === []) {
            try {
                $items = $this->catalog->fetchRemoteItemsForSheet($sheetKey);
                $source = 'remote';

                if ($search !== '') {
                    $term = mb_strtolower(trim($search));
                    $items = array_values(array_filter($items, function (array $item) use ($term): bool {
                        $haystack = mb_strtolower(implode(' ', [
                            (string) ($item['code'] ?? ''),
                            (string) ($item['name'] ?? ''),
                            (string) ($item['category'] ?? ''),
                        ]));

                        return str_contains($haystack, $term);
                    }));
                }
            } catch (\Throwable) {
                $items = [];
            }
        }

        return response()->json([
            'sheet_key' => $sheetKey,
            'items' => $items,
            'total' => count($items),
            'source' => $source,
            'last_synced_at' => $this->catalog->lastSyncedAt()?->toIso8601String(),
        ]);
    }

    public function sync(SupplierCatalogSyncService $sync): JsonResponse
    {
        set_time_limit(300);

        $summary = $sync->syncAll();

        $message = sprintf(
            'Sync selesai: %d tab, %d baru, %d diperbarui, %d dihapus.',
            $summary['sheets'],
            $summary['created'],
            $summary['updated'],
            $summary['removed'],
        );

        if ($summary['failed'] !== []) {
            $message .= ' Gagal: '.implode('; ', $summary['failed']);
        }

        return response()->json([
            'message' => $message,
            'sheets' => $summary['sheets'],
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'removed' => $summary['removed'],
            'failed' => $summary['failed'],
            'last_synced_at' => $this->catalog->lastSyncedAt()?->toIso8601String(),
        ]);
    }
}
