<?php

namespace App\Jobs;

use App\Exports\MemberPaymentsExport;
use App\Exports\MonthlyReportExport;
use App\Exports\ProjectProfitExport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ExportExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly array $filter,
        private readonly User $user,
        private readonly string $exportType,
    ) {}

    public function handle(): void
    {
        $export = match ($this->exportType) {
            'monthly' => new MonthlyReportExport(
                (int) ($this->filter['month'] ?? now()->month),
                (int) ($this->filter['year'] ?? now()->year),
            ),
            'member_payments' => new MemberPaymentsExport($this->filter),
            'project_profit' => new ProjectProfitExport($this->filter),
            default => null,
        };

        if ($export === null) {
            return;
        }

        $fileName = match ($this->exportType) {
            'monthly' => 'laporan-bulanan-'.($this->filter['year'] ?? now()->year).'-'.str_pad($this->filter['month'] ?? now()->month, 2, '0', STR_PAD_LEFT).'.xlsx',
            'member_payments' => 'laporan-anggota-'.now()->format('Y-m-d').'.xlsx',
            'project_profit' => 'laporan-project-'.now()->format('Y-m-d').'.xlsx',
            default => 'export-'.now()->format('Y-m-d-H-i').'.xlsx',
        };

        $path = 'exports/'.$this->user->id.'/'.$fileName;
        Excel::store($export, $path, 'public');

        activity()
            ->performedOn($this->user)
            ->causedBy($this->user)
            ->withProperties(['export_type' => $this->exportType, 'file' => $path])
            ->log('Export Excel selesai: '.$this->exportType);
    }
}
