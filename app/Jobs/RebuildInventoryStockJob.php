<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WarehouseStockRebuildService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildInventoryStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?int $warehouseId = null,
        private readonly ?User $initiator = null,
    ) {}

    public function handle(WarehouseStockRebuildService $rebuildService): void
    {
        $result = $rebuildService->rebuildFromMovements();

        activity()
            ->causedBy($this->initiator)
            ->withProperties([
                'warehouse_id' => $this->warehouseId,
                'updated' => $result['warehouse_rows_updated'] ?? 0,
                'created' => $result['warehouse_rows_created'] ?? 0,
            ])
            ->log('Inventory stock rebuilt');
    }
}
