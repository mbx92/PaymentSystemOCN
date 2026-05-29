<?php

namespace App\Services;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Services\CoaSettingService;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\MasterProduct;
use App\Models\PosSale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CogsBackfillService
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
    ) {}

    public function summarize(): array
    {
        $salesMissingCogs = $this->salesMissingCogs();
        $productsWithoutCost = MasterProduct::query()
            ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
            ->where(function ($q): void {
                $q->whereNull('unit_cost')->orWhere('unit_cost', 0);
            })
            ->count();

        $cogsAccount = app(CoaSettingService::class)
            ->resolveAccountByKey('pos_sale_cogs_account', '5009');
        $inventoryAccount = Account::query()->where('code', '1201')->first();

        return [
            'sales_missing_cogs_count' => $salesMissingCogs->count(),
            'sales_missing_cogs' => $salesMissingCogs->map(fn (PosSale $s) => [
                'number' => $s->number,
                'sold_at' => $s->sold_at?->format('Y-m-d H:i'),
                'grand_total' => (float) $s->grand_total,
                'items_count' => $s->items->count(),
            ]),
            'products_without_cost_count' => $productsWithoutCost,
            'cogs_account_label' => $cogsAccount?->code.' - '.$cogsAccount?->name,
            'inventory_account_label' => $inventoryAccount?->code.' - '.$inventoryAccount?->name,
            'can_run' => $cogsAccount !== null && $inventoryAccount !== null,
            'message' => $cogsAccount === null
                ? 'Akun HPP/COGS belum dikonfigurasi. Buka CoA Settings untuk mengatur.'
                : ($inventoryAccount === null
                    ? 'Akun Persediaan (1201) tidak ditemukan.'
                    : null),
        ];
    }

    public function salesMissingCogs(): Collection
    {
        $sales = PosSale::query()
            ->with('items.product')
            ->where('status', 'paid')
            ->orderByDesc('sold_at')
            ->limit(200)
            ->get();

        $existingRefs = JournalEntry::query()
            ->where('source_module', 'pos_sale_cogs')
            ->whereIn('source_reference', $sales->pluck('number'))
            ->pluck('source_reference');

        return $sales->reject(fn (PosSale $sale) => $existingRefs->contains($sale->number))->values();
    }

    public function backfillUnitCosts(): array
    {
        $products = MasterProduct::query()
            ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
            ->where(function ($q): void {
                $q->whereNull('unit_cost')->orWhere('unit_cost', 0);
            })
            ->get();

        $updated = 0;
        foreach ($products as $product) {
            $price = (float) DB::table('purchase_order_lines')
                ->where('master_product_id', $product->id)
                ->where('unit_price', '>', 0)
                ->orderByDesc('id')
                ->value('unit_price');

            if ($price > 0) {
                $product->update(['unit_cost' => $price]);
                $updated++;
            }
        }

        return [
            'products_checked' => $products->count(),
            'products_updated' => $updated,
        ];
    }

    public function backfillCogs(): array
    {
        $sales = $this->salesMissingCogs();
        $cogsAccount = app(CoaSettingService::class)
            ->resolveAccountByKey('pos_sale_cogs_account', '5009');
        $inventoryAccount = Account::query()->where('code', '1201')->firstOrFail();

        if (! $cogsAccount) {
            return ['succeeded' => 0, 'skipped' => $sales->count(), 'total_cogs_amount' => 0.0, 'errors' => ['Akun HPP/COGS belum dikonfigurasi.']];
        }

        $succeeded = 0;
        $totalAmount = 0.0;
        $errors = [];

        foreach ($sales as $sale) {
            $sale->loadMissing('items.product');

            $totalCogs = 0.0;
            foreach ($sale->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }
                $totalCogs += (float) ($product->unit_cost ?? 0) * (int) ($item->base_qty_used ?? 1);
            }

            if ($totalCogs <= 0) {
                $errors[] = "{$sale->number}: COGS = 0 (set unit_cost produk dulu via utility unit cost)";

                continue;
            }

            try {
                $companyId = $this->resolveSaleCompanyId($sale);
                $this->glPostingService->post(
                    $companyId,
                    sourceModule: 'pos_sale_cogs',
                    sourceReference: $sale->number,
                    description: 'HPP POS '.$sale->number.' (backfill)',
                    entryDate: $sale->sold_at?->toDateString() ?? now()->toDateString(),
                    lines: [
                        ['account_id' => $cogsAccount->id, 'debit' => $totalCogs, 'credit' => 0],
                        ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $totalCogs],
                    ]
                );
                $succeeded++;
                $totalAmount += $totalCogs;
            } catch (\Throwable $e) {
                $errors[] = "{$sale->number}: {$e->getMessage()}";
            }
        }

        return [
            'succeeded' => $succeeded,
            'skipped' => $sales->count() - $succeeded,
            'total_cogs_amount' => $totalAmount,
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    private function resolveSaleCompanyId(PosSale $sale): int
    {
        $entry = JournalEntry::query()
            ->where('source_module', 'pos_sale')
            ->where('source_reference', $sale->number)
            ->first();

        if ($entry?->company_id) {
            return (int) $entry->company_id;
        }

        return ErpCompanyResolver::resolveForGlPosting(request());
    }
}
