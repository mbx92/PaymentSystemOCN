<?php

namespace App\Services;

use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Models\PayablePayment;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\PosSale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class CashflowReportService
{
    public function build(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $source = $this->normalizeOption($request->string('source')->toString(), ['all', 'project', 'manual', 'pos', 'supplier_payment'], 'all');
        $groupBy = $this->normalizeOption($request->string('group_by')->toString(), ['day', 'week', 'month'], 'day');
        $projectId = $request->string('project_id')->toString();
        $projectId = $projectId !== '' && $projectId !== 'all' ? $projectId : null;
        $companyId = ErpCompanyResolver::resolveForReporting($request);

        $cashIns = CashIn::query()
            ->with(['project:id,name', 'paymentMethod:id,name', 'creator:id,name'])
            ->whereDate('date', '>=', $dateFrom->toDateString())
            ->whereDate('date', '<=', $dateTo->toDateString())
            ->when($companyId, fn (Builder $q) => $this->applyCompanyScope($q, $companyId))
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when(in_array($source, ['pos', 'supplier_payment'], true), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($source === 'project', fn ($q) => $q->whereNotNull('project_id'))
            ->when($source === 'manual', fn ($q) => $q->whereNull('project_id'))
            ->orderBy('date')
            ->get();

        $cashOuts = CashOut::query()
            ->with(['project:id,name', 'creator:id,name'])
            ->whereDate('date', '>=', $dateFrom->toDateString())
            ->whereDate('date', '<=', $dateTo->toDateString())
            ->when($companyId, fn (Builder $q) => $this->applyCompanyScope($q, $companyId))
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when(in_array($source, ['pos', 'supplier_payment'], true), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($source === 'project', fn ($q) => $q->whereNotNull('project_id'))
            ->when($source === 'manual', fn ($q) => $q->whereNull('project_id'))
            ->orderBy('date')
            ->get();

        $posSales = $this->posTransactions($request, $dateFrom, $dateTo, $source, $projectId, $companyId);
        $supplierPayments = $this->supplierPaymentTransactions($request, $dateFrom, $dateTo, $source, $projectId, $companyId);

        $transactions = $cashIns->map(function (CashIn $cashIn): array {
            return [
                'id' => $cashIn->id,
                'date' => $cashIn->date?->toDateString(),
                'direction' => 'in',
                'source' => $cashIn->project_id ? 'Project' : 'Manual / Umum',
                'project_id' => $cashIn->project_id,
                'project_name' => $cashIn->project?->name ?? 'Manual / Umum',
                'category' => $cashIn->category,
                'counterparty' => $cashIn->project?->name ?? '-',
                'payment_method' => $cashIn->paymentMethod?->name ?? '-',
                'note' => $cashIn->note,
                'amount' => (float) $cashIn->amount,
                'created_by' => $cashIn->creator?->name ?? '-',
            ];
        })->concat(
            $cashOuts->map(function (CashOut $cashOut): array {
                return [
                    'id' => $cashOut->id,
                    'date' => $cashOut->date?->toDateString(),
                    'direction' => 'out',
                    'source' => $cashOut->project_id ? 'Project' : 'Manual / Umum',
                    'project_id' => $cashOut->project_id,
                    'project_name' => $cashOut->project?->name ?? 'Manual / Umum',
                    'category' => $cashOut->category,
                    'counterparty' => $cashOut->recipient_name ?: '-',
                    'payment_method' => '-',
                    'note' => $cashOut->note,
                    'amount' => (float) $cashOut->amount,
                    'created_by' => $cashOut->creator?->name ?? '-',
                ];
            })
        )->concat($posSales)
            ->concat($supplierPayments)
            ->sortBy([
                ['date', 'asc'],
                ['direction', 'asc'],
            ])->values();

        $totalIn = (float) $transactions->where('direction', 'in')->sum('amount');
        $totalOut = (float) $transactions->where('direction', 'out')->sum('amount');

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'source' => $source,
                'project_id' => $projectId ?? 'all',
                'group_by' => $groupBy,
                'company_id' => $request->query('company_id', $companyId ?? ErpCompanyResolver::ALL_COMPANIES),
            ],
            'summary' => [
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'net_cashflow' => $totalIn - $totalOut,
                'transaction_count' => $transactions->count(),
                'cash_in_count' => $transactions->where('direction', 'in')->count(),
                'cash_out_count' => $transactions->where('direction', 'out')->count(),
            ],
            'pivot' => [
                'timeline' => $this->pivotTimeline($transactions, $groupBy),
                'categories' => $this->pivotByKey($transactions, fn (array $row) => $this->categoryLabel($row['category'])),
                'projects' => $this->pivotByKey($transactions, fn (array $row) => $row['project_name']),
                'sources' => $this->pivotByKey($transactions, fn (array $row) => $row['source']),
            ],
            'transactions' => $this->paginateCollection(
                $transactions->sortByDesc('date')->values(),
                $request,
            ),
        ];
    }

    private function applyCompanyScope(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $scope) use ($companyId): void {
            $scope->whereHas('journalEntry', fn (Builder $journal) => $journal->where('company_id', $companyId))
                ->orWhere(function (Builder $fallback) use ($companyId): void {
                    $fallback->whereHas('creator', fn (Builder $creator) => $creator->where('company_id', $companyId))
                        ->where(function (Builder $journalState): void {
                            $journalState->whereNull('journal_entry_id')
                                ->orWhereHas('journalEntry', fn (Builder $journal) => $journal->whereNull('company_id'));
                        });
                });
        });
    }

    private function posTransactions(Request $request, Carbon $dateFrom, Carbon $dateTo, string $source, ?string $projectId, ?int $companyId): Collection
    {
        if ($projectId || in_array($source, ['project', 'manual', 'supplier_payment'], true)) {
            return collect();
        }

        $sales = PosSale::query()
            ->with(['paymentMethod:id,name', 'soldBy:id,name'])
            ->whereDate('sold_at', '>=', $dateFrom->toDateString())
            ->whereDate('sold_at', '<=', $dateTo->toDateString())
            ->orderBy('sold_at')
            ->get();

        if ($sales->isEmpty()) {
            return collect();
        }

        $journalMap = JournalEntry::query()
            ->whereIn('source_module', ['pos_sale', 'pos_sale_refund', 'pos_sale_reopen'])
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->whereIn('source_reference', $sales->pluck('number')->all())
            ->get(['id', 'source_module', 'source_reference'])
            ->keyBy(fn ($entry) => $entry->source_module.'|'.$entry->source_reference);

        return $sales->map(function (PosSale $sale) use ($companyId, $journalMap): ?array {
            $status = (string) $sale->status;
            $direction = $status === 'refunded' ? 'out' : 'in';
            $sourceModule = match ($status) {
                'refunded' => 'pos_sale_refund',
                'reopened' => 'pos_sale_reopen',
                default => 'pos_sale',
            };
            $journalEntry = $journalMap->get($sourceModule.'|'.$sale->number);

            if ($companyId && ! $journalEntry) {
                return null;
            }

            return [
                'id' => 'pos-'.$sale->id,
                'date' => $sale->sold_at?->toDateString(),
                'direction' => $direction,
                'source' => 'POS',
                'project_id' => null,
                'project_name' => 'POS',
                'category' => $direction === 'in' ? 'penjualan_pos' : 'refund_penjualan_pos',
                'counterparty' => $direction === 'out' ? 'Customer Refund POS' : 'Pelanggan POS',
                'payment_method' => $direction === 'in' ? ($sale->paymentMethod?->name ?? '-') : '-',
                'note' => trim(collect([
                    'Transaksi POS '.$sale->number,
                    $status !== 'paid' ? 'Status '.strtoupper($status) : null,
                    $sale->note,
                ])->filter()->implode(' | ')),
                'amount' => (float) $sale->grand_total,
                'created_by' => $sale->soldBy?->name ?? '-',
            ];
        })->filter()->values();
    }

    private function supplierPaymentTransactions(Request $request, Carbon $dateFrom, Carbon $dateTo, string $source, ?string $projectId, ?int $companyId): Collection
    {
        if ($projectId || in_array($source, ['project', 'manual', 'pos'], true)) {
            return collect();
        }

        return PayablePayment::query()
            ->with(['payable.vendor:id,name', 'payer:id,name'])
            ->whereDate('payment_date', '>=', $dateFrom->toDateString())
            ->whereDate('payment_date', '<=', $dateTo->toDateString())
            ->when($companyId, fn (Builder $q) => $q->whereHas('journalEntry', fn (Builder $journal) => $journal->where('company_id', $companyId)))
            ->orderBy('payment_date')
            ->get()
            ->map(fn (PayablePayment $payment): array => [
                'id' => 'supplier-payment-'.$payment->id,
                'date' => $payment->payment_date?->toDateString(),
                'direction' => 'out',
                'source' => 'Pembayaran Supplier',
                'project_id' => null,
                'project_name' => 'Pembelian',
                'category' => 'pembayaran_hutang_supplier',
                'counterparty' => $payment->payable?->vendor?->name ?? '-',
                'payment_method' => '-',
                'note' => trim(collect([
                    $payment->payable?->bill_no ? 'Bill '.$payment->payable->bill_no : null,
                    $payment->note,
                ])->filter()->implode(' | ')),
                'amount' => (float) $payment->amount,
                'created_by' => $payment->payer?->name ?? '-',
            ]);
    }

    private function paginateCollection(Collection $items, Request $request, string $pageName = 'page'): LengthAwarePaginator
    {
        $perPage = (int) $request->query('per_page', 25);
        $allowed = [25, 50, 75, 100, 125, 150, 175, 200, 225, 250];
        $perPage = in_array($perPage, $allowed, true) ? $perPage : 25;
        $currentPage = Paginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage)->values()->all(),
            $items->count(),
            $perPage,
            $currentPage,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
                'query' => $request->query(),
            ],
        );
    }

    private function resolveDateRange(Request $request): array
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->string('date_from')->toString())->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->string('date_to')->toString())->endOfDay()
            : now()->endOfMonth();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$dateFrom, $dateTo];
    }

    private function normalizeOption(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function pivotByKey(Collection $transactions, callable $resolver): array
    {
        return $transactions
            ->groupBy($resolver)
            ->map(function (Collection $rows, string $label): array {
                $totalIn = (float) $rows->where('direction', 'in')->sum('amount');
                $totalOut = (float) $rows->where('direction', 'out')->sum('amount');

                return [
                    'label' => $label,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'net' => $totalIn - $totalOut,
                    'count' => $rows->count(),
                ];
            })
            ->sortByDesc('net')
            ->values()
            ->all();
    }

    private function pivotTimeline(Collection $transactions, string $groupBy): array
    {
        return $transactions
            ->groupBy(function (array $row) use ($groupBy): string {
                $date = Carbon::parse($row['date']);

                return match ($groupBy) {
                    'month' => $date->format('Y-m'),
                    'week' => $date->format('Y').'-W'.str_pad((string) $date->weekOfYear, 2, '0', STR_PAD_LEFT),
                    default => $date->toDateString(),
                };
            })
            ->map(function (Collection $rows, string $bucket): array {
                $totalIn = (float) $rows->where('direction', 'in')->sum('amount');
                $totalOut = (float) $rows->where('direction', 'out')->sum('amount');

                return [
                    'bucket' => $bucket,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'net' => $totalIn - $totalOut,
                    'count' => $rows->count(),
                ];
            })
            ->sortBy('bucket')
            ->values()
            ->all();
    }

    private function categoryLabel(?string $category): string
    {
        $key = (string) $category;

        return match ($key) {
            'pendapatan_jasa' => 'Pendapatan Jasa',
            'pendapatan_project' => 'Pendapatan Project',
            'penjualan_pos' => 'Penjualan POS',
            'refund_penjualan_pos' => 'Refund Penjualan POS',
            'biaya_tim' => 'Biaya Tim',
            'komisi_referral' => 'Komisi Referral',
            'operasional' => 'Operasional',
            'pembayaran_hutang_supplier' => 'Pembayaran Hutang Supplier',
            'lainnya' => 'Lainnya',
            default => $key !== '' ? str($key)->replace('_', ' ')->title()->toString() : 'Tanpa Kategori',
        };
    }
}
