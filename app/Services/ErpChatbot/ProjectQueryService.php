<?php

namespace App\Services\ErpChatbot;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectQueryService
{
    public function activeProjects(int $limit = 10): Collection
    {
        return Project::query()
            ->where('status', 'berjalan')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'client_name', 'total_value', 'started_at']);
    }

    public function findCompletedProjectByInvoiceNumber(string $invoiceNumber): ?Project
    {
        $project = Project::query()
            ->where('status', 'selesai')
            ->whereRaw('LOWER(invoice_number) = ?', [Str::lower($invoiceNumber)])
            ->first();

        if ($project) {
            return $project;
        }

        return Project::query()
            ->where('status', 'selesai')
            ->latest('finished_at')
            ->limit(200)
            ->get()
            ->first(function (Project $candidate) use ($invoiceNumber): bool {
                return Str::lower($this->invoiceNumber($candidate)) === Str::lower($invoiceNumber);
            });
    }

    private function invoiceNumber(Project $project): string
    {
        return $project->invoice_number
            ?: ('INV-PRJ-'.($project->finished_at?->format('Ymd') ?? $project->created_at?->format('Ymd') ?? now()->format('Ymd')).'-'.strtoupper(substr(str_replace('-', '', (string) $project->getKey()), -6)));
    }
}
