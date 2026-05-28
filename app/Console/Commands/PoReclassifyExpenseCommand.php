<?php

namespace App\Console\Commands;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Purchasing\Models\GoodsReceipt;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CategoryCoaMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PoReclassifyExpenseCommand extends Command
{
    protected $signature = 'po:reclassify-expense {po-number : Nomor PO yang akan direklasifikasi}';

    protected $description = 'Reclassify an existing purchase order GRN from inventory to expense account, creating a correcting journal entry';

    public function handle(GlPostingService $glPostingService): int
    {
        $poNumber = $this->argument('po-number');

        $po = PurchaseOrder::query()
            ->with('receipts')
            ->where('number', $poNumber)
            ->first();

        if (! $po) {
            $this->error("PO {$poNumber} tidak ditemukan.");

            return self::FAILURE;
        }

        if ($po->isExpense()) {
            $this->warn("PO {$poNumber} sudah dikategorikan sebagai expense.");

            return self::SUCCESS;
        }

        $postedReceipt = $po->receipts
            ->firstWhere('status', DocumentStatus::Posted->value);

        if (! $postedReceipt) {
            $this->warn("PO {$poNumber} belum memiliki goods receipt yang diposting. Tidak ada jurnal yang perlu diperbaiki.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($po, $postedReceipt, $glPostingService): void {
            $inventoryAccount = Account::query()->where('code', '1201')->firstOrFail();
            $expenseMapping = CategoryCoaMapping::query()
                ->where('domain', 'purchase_order')
                ->where('category', 'expense')
                ->value('account_id');

            $expenseAccountId = $expenseMapping
                ? (int) $expenseMapping
                : $inventoryAccount->id;

            if ($expenseAccountId === $inventoryAccount->id) {
                $this->warn('Mapping akun expense untuk purchase_order belum dikonfigurasi. Gunakan default akun inventory.');

                return;
            }

            $amount = (float) $po->total_amount;
            $entryDate = $postedReceipt->received_date?->toDateString() ?? now()->toDateString();

            $glPostingService->post(
                ErpCompanyResolver::resolveForGlPosting(request()),
                sourceModule: 'purchasing_reclassify',
                sourceReference: $po->number,
                description: 'Reclassifikasi biaya PO '.$po->number.' dari inventory ke expense',
                entryDate: $entryDate,
                lines: [
                    ['account_id' => $expenseAccountId, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $amount],
                ]
            );

            $po->update(['po_category' => 'expense']);

            $this->info("PO {$po->number} berhasil direklasifikasi ke expense.");
            $this->info("Jurnal koreksi: Debit expense (akun #{$expenseAccountId}), Kredit inventory (akun #{$inventoryAccount->id}), Rp ".number_format($amount, 0, ',', '.'));
        });

        return self::SUCCESS;
    }
}
