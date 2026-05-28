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
        if ($this->warehouseId) {
            $rebuildService->rebuild($this->warehouseId);
        } else {
            $rebuildService->rebuildAll();
        }

        activity()
            ->causedBy($this->initiator)
            ->withProperties(['warehouse_id' => $this->warehouseId])
            ->log('Inventory stock rebuilt');
    }
}
