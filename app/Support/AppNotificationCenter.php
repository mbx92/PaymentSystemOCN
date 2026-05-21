<?php

namespace App\Support;

use App\ERP\Accounting\Models\Payable;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\UserNotificationRead;
use App\Services\WarehouseStockRebuildService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AppNotificationCenter
{
    /**
     * @return array<string, mixed>
     */
    public function buildFor(?User $user): array
    {
        if (! $user) {
            return [
                'total_count' => 0,
                'groups' => [],
                'items' => [],
            ];
        }

        $reads = UserNotificationRead::query()
            ->where('user_id', $user->id)
            ->pluck('read_at', 'notification_id');

        $groups = array_values(array_filter([
            $this->lowStockGroup($reads),
            $this->reservedStockGroup($reads),
            $this->stockMismatchGroup($reads),
            $this->projectTaskGroup($user, $reads),
            $this->supplierBillGroup($reads),
            $this->purchaseOrderEtaGroup($reads),
        ]));

        $items = collect($groups)
            ->flatMap(fn (array $group) => $group['items'] ?? [])
            ->sortByDesc(fn (array $item) => (int) ($item['sort_ts'] ?? 0))
            ->values()
            ->all();

        return [
            'total_count' => array_sum(array_map(fn (array $group) => (int) $group['unread_count'], $groups)),
            'groups' => $groups,
            'items' => $items,
        ];
    }

    private function lowStockGroup(Collection $reads): ?array
    {
        $baseQuery = MasterProduct::query()
            ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
            ->where('low_stock_alert_enabled', true)
            ->whereColumn('stock', '<=', 'min_stock');

        $count = (clone $baseQuery)->count();
        if ($count === 0) {
            return null;
        }

        $items = (clone $baseQuery)
            ->orderBy('stock')
            ->limit(5)
            ->get(['id', 'sku', 'name', 'stock', 'min_stock', 'updated_at'])
            ->map(fn (MasterProduct $product) => $this->makeItem(
                $reads,
                'low-stock-'.$product->id,
                $product->name,
                trim(implode(' · ', array_filter([
                    $product->sku,
                    'Stok '.((float) $product->stock + 0).' / Min '.((float) $product->min_stock + 0),
                ]))),
                'warning',
                route('erp.inventory.stock-management', ['low_stock_only' => 1]),
                $product->updated_at?->timestamp,
            ))
            ->values()
            ->all();

        return $this->makeGroup(
            'low_stock',
            'Stok rendah',
            route('erp.inventory.stock-management', ['low_stock_only' => 1]),
            $items,
            $count,
        );
    }

    private function reservedStockGroup(Collection $reads): ?array
    {
        $baseQuery = MasterProductWarehouseStock::query()
            ->with(['product:id,sku,name', 'warehouse:id,code,name'])
            ->where('reserved_qty', '>', 0);

        $count = (clone $baseQuery)->count();
        if ($count === 0) {
            return null;
        }

        $items = (clone $baseQuery)
            ->orderByDesc('reserved_qty')
            ->limit(5)
            ->get(['id', 'master_product_id', 'warehouse_id', 'reserved_qty', 'updated_at'])
            ->map(fn (MasterProductWarehouseStock $stock) => $this->makeItem(
                $reads,
                'reserved-stock-'.$stock->id,
                $stock->product?->name ?? 'Reserved stock',
                trim(implode(' · ', array_filter([
                    $stock->product?->sku,
                    $stock->warehouse?->code ? 'Warehouse '.$stock->warehouse->code : null,
                    'Reserved '.((float) $stock->reserved_qty + 0),
                ]))),
                'warning',
                route('erp.inventory.stock-management', ['warehouse_id' => $stock->warehouse_id]),
                $stock->updated_at?->timestamp,
            ))
            ->values()
            ->all();

        return $this->makeGroup(
            'reserved_stock',
            'Reserved stock',
            route('erp.inventory.stock-management'),
            $items,
            $count,
        );
    }

    private function stockMismatchGroup(Collection $reads): ?array
    {
        $summary = app(WarehouseStockRebuildService::class)->mismatchSummary();
        $mismatchProducts = $summary['by_product'] ?? [];

        if ($mismatchProducts === [] || (int) ($summary['count'] ?? 0) === 0) {
            return null;
        }

        $productIds = array_keys($mismatchProducts);

        $items = MasterProductWarehouseStock::query()
            ->with(['product:id,sku,name', 'warehouse:id,code,name'])
            ->whereIn('master_product_id', $productIds)
            ->get(['id', 'master_product_id', 'warehouse_id', 'qty', 'updated_at'])
            ->filter(function (MasterProductWarehouseStock $stock) use ($mismatchProducts): bool {
                $mismatch = $mismatchProducts[$stock->master_product_id] ?? null;

                return $mismatch !== null && abs((float) $stock->qty - (float) $mismatch['expected_qty']) > 0.00001;
            })
            ->sortByDesc(fn (MasterProductWarehouseStock $stock) => abs((float) ($mismatchProducts[$stock->master_product_id]['delta_qty'] ?? 0)))
            ->take(5)
            ->map(function (MasterProductWarehouseStock $stock) use ($reads, $mismatchProducts): array {
                $mismatch = $mismatchProducts[$stock->master_product_id] ?? [];

                return $this->makeItem(
                    $reads,
                    'stock-mismatch-'.$stock->id,
                    $stock->product?->name ?? 'Stock mismatch',
                    trim(implode(' · ', array_filter([
                        $stock->product?->sku,
                        $stock->warehouse?->code ? 'Warehouse '.$stock->warehouse->code : null,
                        'Aktual '.((float) $stock->qty + 0),
                        'Expected '.((float) ($mismatch['expected_qty'] ?? 0) + 0),
                    ]))),
                    'error',
                    route('erp.inventory.stock-management', ['warehouse_id' => $stock->warehouse_id]),
                    $stock->updated_at?->timestamp,
                );
            })
            ->values()
            ->all();

        return $this->makeGroup(
            'stock_mismatch',
            'Mismatch stock movement',
            route('erp.inventory.stock-management'),
            $items,
            (int) $summary['count'],
        );
    }

    private function projectTaskGroup(User $user, Collection $reads): ?array
    {
        $today = now()->startOfDay();
        $soon = now()->addDays(3)->endOfDay();

        $baseQuery = ProjectTask::query()
            ->with('project:id,name')
            ->whereNotNull('due_date')
            ->where('status', '!=', 'done')
            ->whereDate('due_date', '<=', $soon);

        if (! $user->hasAnyRole(['admin', 'manajer', 'project'])) {
            $baseQuery->where('assigned_user_id', $user->id);
        }

        $count = (clone $baseQuery)->count();
        if ($count === 0) {
            return null;
        }

        $items = (clone $baseQuery)
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'project_id', 'title', 'due_date', 'updated_at'])
            ->map(function (ProjectTask $task) use ($today, $reads): array {
                $dueDate = $task->due_date?->copy()->startOfDay();
                $isOverdue = $dueDate !== null && $dueDate->lt($today);

                return $this->makeItem(
                    $reads,
                    'project-task-'.$task->id,
                    $task->title,
                    trim(implode(' · ', array_filter([
                        $task->project?->name,
                        $this->dateMetaLabel($dueDate, 'Due'),
                    ]))),
                    $isOverdue ? 'error' : 'info',
                    route('projects.show', $task->project_id).'#tasks',
                    $task->updated_at?->timestamp ?? $dueDate?->timestamp,
                );
            })
            ->values()
            ->all();

        return $this->makeGroup(
            'project_tasks',
            'Task project',
            route('erp.projects'),
            $items,
            $count,
        );
    }

    private function supplierBillGroup(Collection $reads): ?array
    {
        $today = now()->startOfDay();
        $soon = now()->addDays(7)->endOfDay();

        $baseQuery = Payable::query()
            ->with('vendor:id,name,code')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $soon)
            ->whereColumn('paid_amount', '<', 'amount')
            ->whereNotIn('status', [DocumentStatus::Paid->value, DocumentStatus::Void->value]);

        $count = (clone $baseQuery)->count();
        if ($count === 0) {
            return null;
        }

        $items = (clone $baseQuery)
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'vendor_id', 'bill_no', 'due_date', 'amount', 'paid_amount', 'updated_at'])
            ->map(function (Payable $payable) use ($today, $reads): array {
                $dueDate = $payable->due_date?->copy()->startOfDay();
                $remaining = (float) $payable->amount - (float) $payable->paid_amount;
                $isOverdue = $dueDate !== null && $dueDate->lt($today);

                return $this->makeItem(
                    $reads,
                    'payable-'.$payable->id,
                    'Bill '.$payable->bill_no,
                    trim(implode(' · ', array_filter([
                        $payable->vendor?->name,
                        $this->dateMetaLabel($dueDate, 'Jatuh tempo'),
                        'Sisa Rp '.number_format($remaining, 0, ',', '.'),
                    ]))),
                    $isOverdue ? 'error' : 'warning',
                    route('erp.accounting.payments'),
                    $payable->updated_at?->timestamp ?? $dueDate?->timestamp,
                );
            })
            ->values()
            ->all();

        return $this->makeGroup(
            'supplier_bills',
            'Hutang supplier',
            route('erp.accounting.payments'),
            $items,
            $count,
        );
    }

    private function purchaseOrderEtaGroup(Collection $reads): ?array
    {
        $today = now()->startOfDay();
        $soon = now()->addDays(3)->endOfDay();

        $baseQuery = PurchaseOrder::query()
            ->with('vendor:id,name,code')
            ->whereNotNull('eta_date')
            ->whereDate('eta_date', '<=', $soon)
            ->whereIn('status', [
                DocumentStatus::Submitted->value,
                DocumentStatus::Approved->value,
                DocumentStatus::Posted->value,
                DocumentStatus::PartiallyPaid->value,
            ]);

        $count = (clone $baseQuery)->count();
        if ($count === 0) {
            return null;
        }

        $items = (clone $baseQuery)
            ->orderBy('eta_date')
            ->limit(5)
            ->get(['id', 'number', 'vendor_id', 'eta_date', 'status', 'updated_at'])
            ->map(function (PurchaseOrder $purchaseOrder) use ($today, $reads): array {
                $etaDate = $purchaseOrder->eta_date?->copy()->startOfDay();
                $isOverdue = $etaDate !== null && $etaDate->lt($today);

                return $this->makeItem(
                    $reads,
                    'purchase-order-'.$purchaseOrder->id,
                    'PO '.$purchaseOrder->number,
                    trim(implode(' · ', array_filter([
                        $purchaseOrder->vendor?->name,
                        $this->dateMetaLabel($etaDate, 'ETA'),
                        'Status '.ucfirst((string) $purchaseOrder->status->value),
                    ]))),
                    $isOverdue ? 'warning' : 'info',
                    route('erp.purchasing.purchase-orders.show', $purchaseOrder),
                    $purchaseOrder->updated_at?->timestamp ?? $etaDate?->timestamp,
                );
            })
            ->values()
            ->all();

        return $this->makeGroup(
            'purchase_orders',
            'PO perlu dipantau',
            route('erp.purchasing.purchase-orders'),
            $items,
            $count,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function makeItem(
        Collection $reads,
        string $notificationId,
        string $title,
        ?string $meta,
        string $severity,
        string $href,
        ?int $sortTs = null,
    ): array {
        $readAt = $reads->get($notificationId);

        return [
            'notification_id' => $notificationId,
            'title' => $title,
            'meta' => $meta,
            'severity' => $severity,
            'href' => $href,
            'read' => $readAt !== null,
            'read_at' => $readAt ? Carbon::parse($readAt)->toIso8601String() : null,
            'sort_ts' => $sortTs ?? now()->timestamp,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function makeGroup(string $key, string $label, string $href, array $items, int $count): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'unread_count' => collect($items)->where('read', false)->count(),
            'href' => $href,
            'items' => $items,
        ];
    }

    private function dateMetaLabel(?Carbon $date, string $prefix): ?string
    {
        if (! $date) {
            return null;
        }

        $today = now()->startOfDay();
        $days = $today->diffInDays($date, false);
        $relative = match (true) {
            $days < 0 => abs($days).' hari lalu',
            $days === 0 => 'hari ini',
            default => $days.' hari lagi',
        };

        return $prefix.' '.$date->format('d M Y').' ('.$relative.')';
    }
}
