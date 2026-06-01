<?php

namespace App\Services\ErpChatbot;

use App\Models\InvoiceSendLog;
use App\Models\ProjectPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class InvoiceQueryService
{
    public function supportsProjectPaymentDueDate(): bool
    {
        return Schema::hasColumn('project_payments', 'due_date');
    }

    public function unpaidProjectPayments(int $limit = 8): Collection
    {
        return ProjectPayment::query()
            ->with('project:id,name,invoice_number')
            ->whereNull('paid_at')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get();
    }

    public function dueProjectPaymentsWithinDays(int $days, int $limit = 8): Collection
    {
        if (! $this->supportsProjectPaymentDueDate()) {
            return collect();
        }

        return ProjectPayment::query()
            ->with('project:id,name,invoice_number')
            ->whereNull('paid_at')
            ->where(function ($query) use ($days): void {
                $query
                    ->whereNull('due_date')
                    ->orWhere('due_date', '<=', now()->addDays($days)->toDateString());
            })
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }

    public function recentSendLogs(int $limit = 10): Collection
    {
        return InvoiceSendLog::query()
            ->latest('sent_at')
            ->latest('id')
            ->limit($limit)
            ->get(['invoice_number', 'recipient_email', 'status', 'sent_at']);
    }
}
