<?php

namespace App\Exports;

use App\Models\TeamDistribution;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class MemberPaymentsExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return TeamDistribution::with(['project', 'user'])
            ->when($this->filters['user_id'] ?? null, fn ($q) => $q->where('user_id', $this->filters['user_id']))
            ->when($this->filters['year'] ?? null, fn ($q) => $q->whereYear('created_at', $this->filters['year']))
            ->get();
    }

    public function headings(): array
    {
        return ['Nama Anggota', 'Project', 'Status Project', 'Peran', 'Persentase (%)', 'Base Pay', 'Bonus', 'Total'];
    }

    public function map($row): array
    {
        return [
            $row->user->name, $row->project->name, $row->project->status,
            $row->role_in_project, $row->percentage, $row->base_pay, $row->bonus, $row->total_pay,
        ];
    }

    public function title(): string
    {
        return 'Pembayaran Anggota Tim';
    }
}
