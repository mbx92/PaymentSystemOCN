<?php

namespace App\Services\ErpChatbot;

use App\Models\InvoiceSendLog;
use App\Models\ProjectPayment;
use Illuminate\Support\Collection;

class InvoiceQueryService
{
    public function unpaidProjectPayments(int $limit = 8): Collection
    {
        return ProjectPayment::query()
            ->with('project:id,name,invoice_number')
            ->whereNull('paid_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function dueProjectPaymentsWithinDays(int $days, int $limit = 8): Collection
    {
        return ProjectPayment::query()
            ->with('project:id,name,invoice_number')
            ->whereNull('paid_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays($days)->toDateString())
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
