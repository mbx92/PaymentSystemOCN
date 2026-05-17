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
            ->when(($this->filters['status'] ?? null) === 'unpaid', fn ($q) => $q->whereNull('paid_at'))
            ->when(($this->filters['status'] ?? null) === 'paid', fn ($q) => $q->whereNotNull('paid_at'))
            ->get();
    }

    public function headings(): array
    {
        return ['Nama Anggota', 'Project', 'Status Project', 'Peran', 'Persentase (%)', 'Base Pay', 'Bonus', 'Total', 'Status Bayar', 'Tgl Bayar'];
    }

    public function map($row): array
    {
        return [
            $row->user->name, $row->project->name, $row->project->status,
            $row->role_in_project, $row->percentage, $row->base_pay, $row->bonus, $row->total_pay,
            $row->isPaid() ? 'Sudah dibayar' : 'Belum dibayar',
            $row->paid_at?->format('Y-m-d') ?? '-',
        ];
    }

    public function title(): string
    {
        return 'Pembayaran Anggota Tim';
    }
}
