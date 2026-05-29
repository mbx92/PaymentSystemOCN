<?php

namespace App\Exports;

use App\Models\CashIn;
use App\Models\CashOut;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class MonthlyReportExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private int $month, private int $year) {}

    public function collection()
    {
        $ins = CashIn::with('project')->whereYear('date', $this->year)->whereMonth('date', $this->month)->get()
            ->map(fn ($c) => (object) ['type' => 'MASUK', 'project' => $c->project?->name ?? 'Manual / Umum', 'category' => $c->category, 'amount' => $c->amount, 'date' => $c->date->format('Y-m-d'), 'note' => $c->note, 'recipient' => '-']);
        $outs = CashOut::with('project')->whereYear('date', $this->year)->whereMonth('date', $this->month)->get()
            ->map(fn ($c) => (object) ['type' => 'KELUAR', 'project' => $c->project?->name ?? 'Operasional Umum', 'category' => $c->category, 'amount' => $c->amount, 'date' => $c->date->format('Y-m-d'), 'note' => $c->note, 'recipient' => $c->recipient_name ?? '-']);

        return $ins->merge($outs)->sortBy('date');
    }

    public function headings(): array
    {
        return ['Tipe', 'Project', 'Kategori', 'Jumlah', 'Tanggal', 'Keterangan', 'Penerima'];
    }

    public function map($row): array
    {
        return [$row->type, $row->project, $row->category, $row->amount, $row->date, $row->note, $row->recipient];
    }

    public function title(): string
    {
        return 'Rekap Bulan '.$this->month.'-'.$this->year;
    }
}
