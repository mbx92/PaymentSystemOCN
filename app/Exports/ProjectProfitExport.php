<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProjectProfitExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return Project::with(['cashIns', 'cashOuts', 'referrals'])
            ->whereIn('status', ['berjalan', 'selesai'])
            ->when($this->filters['search'] ?? null, fn ($q) => $q->where('name', 'ilike', "%{$this->filters['search']}%"))
            ->get();
    }

    public function headings(): array
    {
        return ['Project', 'Klien', 'Status', 'Nilai Kontrak', 'Kas Masuk', 'Komisi Referral', 'Biaya Tim', 'Operasional', 'Kas Keluar', 'Laba', 'Margin (%)'];
    }

    public function map($row): array
    {
        $cashIn = $row->cashIns->sum('amount');
        $cashOut = $row->cashOuts->sum('amount');
        $profit = $cashIn - $cashOut;
        $margin = $cashIn > 0 ? round($profit / $cashIn * 100, 1) : 0;
        $referral = $row->referrals->sum('commission_amount');
        $operational = $row->cashOuts->where('category', 'operasional')->sum('amount');
        $teamCost = $row->cashOuts->where('category', 'biaya_tim')->sum('amount');

        return [
            $row->name, $row->client_name, $row->status,
            $row->total_value, $cashIn, $referral, $teamCost, $operational, $cashOut, $profit, $margin,
        ];
    }

    public function title(): string
    {
        return 'Laporan Laba Project';
    }
}
