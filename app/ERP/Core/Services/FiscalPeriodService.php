<?php

namespace App\ERP\Core\Services;

use App\ERP\Core\Models\FiscalPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FiscalPeriodService
{
    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_YEARLY = 'yearly';

    /**
     * @return array{
     *     yearly: array<string, mixed>,
     *     monthly: list<array<string, mixed>>
     * }
     */
    public function periodRowsForYear(int $companyId, int $year): array
    {
        $existing = FiscalPeriod::query()
            ->with(['company:id,name', 'closer:id,name'])
            ->where('company_id', $companyId)
            ->where('period_year', $year)
            ->get()
            ->keyBy(fn (FiscalPeriod $period): string => $period->period_type.'-'.$period->period_month);

        $journalCounts = $this->journalCountsByMonth($companyId, $year);

        return [
            'yearly' => $this->toPayload(
                $existing->get(self::TYPE_YEARLY.'-0'),
                $companyId,
                self::TYPE_YEARLY,
                $year,
                0,
                (int) $journalCounts->sum()
            ),
            'monthly' => collect(range(1, 12))
                ->map(fn (int $month): array => $this->toPayload(
                    $existing->get(self::TYPE_MONTHLY.'-'.$month),
                    $companyId,
                    self::TYPE_MONTHLY,
                    $year,
                    $month,
                    (int) ($journalCounts[$month] ?? 0)
                ))
                ->all(),
        ];
    }

    public function close(int $companyId, string $periodType, int $year, ?int $month, int $actorId, ?string $notes = null): FiscalPeriod
    {
        [$resolvedMonth, $startDate, $endDate] = $this->resolvePeriodDefinition($periodType, $year, $month);

        return FiscalPeriod::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'period_type' => $periodType,
                'period_year' => $year,
                'period_month' => $resolvedMonth,
            ],
            [
                'name' => $this->defaultName($periodType, $year, $resolvedMonth),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'is_closed' => true,
                'closed_at' => now(),
                'closed_by' => $actorId,
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            ],
        );
    }

    public function reopen(FiscalPeriod $period): FiscalPeriod
    {
        $period->update([
            'is_closed' => false,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $period->fresh(['company:id,name', 'closer:id,name']);
    }

    public function ensureDateIsOpen(string|\DateTimeInterface $date, ?int $companyId, string $field = 'date', string $actionLabel = 'Transaksi accounting'): void
    {
        if (! $companyId) {
            return;
        }

        $resolvedDate = $date instanceof \DateTimeInterface
            ? Carbon::instance(Carbon::parse($date))
            : Carbon::parse($date);

        $closedPeriod = FiscalPeriod::query()
            ->where('company_id', $companyId)
            ->where('is_closed', true)
            ->whereDate('start_date', '<=', $resolvedDate->toDateString())
            ->whereDate('end_date', '>=', $resolvedDate->toDateString())
            ->orderByRaw("CASE WHEN period_type = 'monthly' THEN 0 ELSE 1 END")
            ->orderByDesc('end_date')
            ->first();

        if (! $closedPeriod) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $actionLabel.' tanggal '.$resolvedDate->format('d/m/Y').' ditolak karena periode '.$this->humanPeriodLabel($closedPeriod).' sudah ditutup.',
        ]);
    }

    /**
     * @return array{0:int,1:Carbon,2:Carbon}
     */
    private function resolvePeriodDefinition(string $periodType, int $year, ?int $month): array
    {
        if ($periodType === self::TYPE_YEARLY) {
            return [
                0,
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ];
        }

        if ($month === null || $month < 1 || $month > 12) {
            throw ValidationException::withMessages([
                'month' => 'Bulan tutup buku harus di antara 1 sampai 12.',
            ]);
        }

        return [
            $month,
            Carbon::create($year, $month, 1)->startOfDay(),
            Carbon::create($year, $month, 1)->endOfMonth()->endOfDay(),
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function journalCountsByMonth(int $companyId, int $year): Collection
    {
        return \App\ERP\Accounting\Models\JournalEntry::query()
            ->where('company_id', $companyId)
            ->whereYear('entry_date', $year)
            ->get(['entry_date'])
            ->groupBy(fn ($entry) => (int) $entry->entry_date?->format('n'))
            ->map(fn (Collection $rows) => $rows->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(?FiscalPeriod $period, int $companyId, string $periodType, int $year, int $month, int $journalCount): array
    {
        $startDate = $period?->start_date ?? ($periodType === self::TYPE_YEARLY
            ? Carbon::create($year, 1, 1)
            : Carbon::create($year, $month, 1));
        $endDate = $period?->end_date ?? ($periodType === self::TYPE_YEARLY
            ? Carbon::create($year, 12, 31)
            : Carbon::create($year, $month, 1)->endOfMonth());

        return [
            'id' => $period?->id,
            'company_id' => $period?->company_id ?? $companyId,
            'name' => $period?->name ?? $this->defaultName($periodType, $year, $month),
            'period_type' => $period?->period_type ?? $periodType,
            'period_year' => $period?->period_year ?? $year,
            'period_month' => $period?->period_month ?? $month,
            'start_date' => $startDate?->toDateString(),
            'end_date' => $endDate?->toDateString(),
            'is_closed' => (bool) ($period?->is_closed ?? false),
            'closed_at' => $period?->closed_at?->toDateTimeString(),
            'closed_by_name' => $period?->closer?->name,
            'notes' => $period?->notes,
            'journal_count' => $journalCount,
            'label' => $periodType === self::TYPE_YEARLY
                ? 'Tahun '.$year
                : Carbon::create($year, $month, 1)->translatedFormat('F Y'),
        ];
    }

    private function defaultName(string $periodType, int $year, int $month): string
    {
        if ($periodType === self::TYPE_YEARLY) {
            return 'Tutup buku tahunan '.$year;
        }

        return 'Tutup buku '.Carbon::create($year, $month, 1)->translatedFormat('F Y');
    }

    private function humanPeriodLabel(FiscalPeriod $period): string
    {
        if ($period->period_type === self::TYPE_YEARLY) {
            return 'tahun '.$period->period_year;
        }

        return Carbon::create($period->period_year, max($period->period_month, 1), 1)->translatedFormat('F Y');
    }
}
