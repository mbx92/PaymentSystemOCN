<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Payable;
use App\ERP\Accounting\Models\Receivable;
use App\ERP\CRM\Models\CrmActivity;
use App\ERP\CRM\Models\CrmPipeline;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\ProjectTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ErpCalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $events = collect();

        $this->addProjectEvents($events, $start, $end);
        $this->addTaskEvents($events, $start, $end);
        $this->addPaymentEvents($events, $start, $end);
        $this->addPurchaseOrderEvents($events, $start, $end);
        $this->addReceivableEvents($events, $start, $end);
        $this->addPayableEvents($events, $start, $end);
        $this->addPipelineEvents($events, $start, $end);
        $this->addActivityEvents($events, $start, $end);

        return Inertia::render('ERP/Calendar/Index', [
            'events' => $events->sortBy('date')->values(),
            'month' => $month,
            'year' => $year,
        ]);
    }

    private function addProjectEvents($events, Carbon $start, Carbon $end): void
    {
        $projects = Project::query()
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('started_at', [$start, $end])
                    ->orWhereBetween('finished_at', [$start, $end]);
            })
            ->get(['id', 'name', 'client_name', 'status', 'started_at', 'finished_at']);

        foreach ($projects as $p) {
            if ($p->started_at && $p->started_at->between($start, $end)) {
                $events->push([
                    'date' => $p->started_at->format('Y-m-d'),
                    'title' => $p->name,
                    'subtitle' => $p->client_name,
                    'type' => 'project_start',
                    'color' => 'primary',
                    'label' => 'Project Mulai',
                    'meta' => "Status: {$p->status}",
                ]);
            }
            if ($p->finished_at && $p->finished_at->between($start, $end)) {
                $events->push([
                    'date' => $p->finished_at->format('Y-m-d'),
                    'title' => $p->name,
                    'subtitle' => $p->client_name,
                    'type' => 'project_end',
                    'color' => 'success',
                    'label' => 'Project Selesai',
                    'meta' => "Status: {$p->status}",
                ]);
            }
        }
    }

    private function addTaskEvents($events, Carbon $start, Carbon $end): void
    {
        $tasks = ProjectTask::query()
            ->with(['project:id,name', 'assignee:id,name'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start, $end])
            ->get(['id', 'project_id', 'title', 'status', 'assigned_user_id', 'due_date']);

        foreach ($tasks as $task) {
            $events->push([
                'date' => $task->due_date->format('Y-m-d'),
                'title' => $task->title,
                'subtitle' => $task->project?->name,
                'type' => 'task_due',
                'color' => $task->status === 'done' ? 'success' : 'warning',
                'label' => 'Deadline Task',
                'meta' => ($task->assignee?->name ? "PIC: {$task->assignee->name} · " : '').$task->status,
            ]);
        }
    }

    private function addPaymentEvents($events, Carbon $start, Carbon $end): void
    {
        $payments = ProjectPayment::query()
            ->with('project:id,name,client_name')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'project_id', 'term_number', 'amount', 'paid_at']);

        foreach ($payments as $pay) {
            $events->push([
                'date' => $pay->paid_at->format('Y-m-d'),
                'title' => "Termin {$pay->term_number} — {$pay->project?->name}",
                'subtitle' => $pay->project?->client_name,
                'type' => 'payment',
                'color' => 'accent',
                'label' => 'Pembayaran Termin',
                'meta' => 'Rp '.number_format((float) $pay->amount, 0, ',', '.'),
            ]);
        }
    }

    private function addPurchaseOrderEvents($events, Carbon $start, Carbon $end): void
    {
        $pos = PurchaseOrder::query()
            ->with('vendor:id,name')
            ->whereNotNull('eta_date')
            ->whereBetween('eta_date', [$start, $end])
            ->get(['id', 'number', 'vendor_id', 'eta_date', 'total_amount', 'status']);

        foreach ($pos as $po) {
            $events->push([
                'date' => $po->eta_date->format('Y-m-d'),
                'title' => "PO {$po->number}",
                'subtitle' => $po->vendor?->name,
                'type' => 'po_eta',
                'color' => 'warning',
                'label' => 'ETA Purchase Order',
                'meta' => "Status: {$po->status->value}",
            ]);
        }
    }

    private function addReceivableEvents($events, Carbon $start, Carbon $end): void
    {
        $receivables = Receivable::query()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start, $end])
            ->get(['id', 'invoice_no', 'due_date', 'amount', 'paid_amount', 'status']);

        foreach ($receivables as $ar) {
            $remaining = (float) $ar->amount - (float) $ar->paid_amount;
            if ($remaining <= 0) {
                continue;
            }
            $events->push([
                'date' => $ar->due_date->format('Y-m-d'),
                'title' => "Invoice {$ar->invoice_no}",
                'subtitle' => null,
                'type' => 'receivable_due',
                'color' => 'accent',
                'label' => 'Jatuh Tempo Piutang',
                'meta' => 'Sisa: Rp '.number_format($remaining, 0, ',', '.'),
            ]);
        }
    }

    private function addPayableEvents($events, Carbon $start, Carbon $end): void
    {
        $payables = Payable::query()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start, $end])
            ->get(['id', 'bill_no', 'due_date', 'amount', 'paid_amount', 'status']);

        foreach ($payables as $ap) {
            $remaining = (float) $ap->amount - (float) $ap->paid_amount;
            if ($remaining <= 0) {
                continue;
            }
            $events->push([
                'date' => $ap->due_date->format('Y-m-d'),
                'title' => "Bill {$ap->bill_no}",
                'subtitle' => null,
                'type' => 'payable_due',
                'color' => 'error',
                'label' => 'Jatuh Tempo Hutang',
                'meta' => 'Sisa: Rp '.number_format($remaining, 0, ',', '.'),
            ]);
        }
    }

    private function addPipelineEvents($events, Carbon $start, Carbon $end): void
    {
        $pipelines = CrmPipeline::query()
            ->with(['customer:id,name,company', 'pic:id,name'])
            ->whereNotNull('expected_close_date')
            ->whereBetween('expected_close_date', [$start, $end])
            ->get(['id', 'code', 'title', 'crm_customer_id', 'pic_user_id', 'stage', 'deal_value', 'expected_close_date']);

        foreach ($pipelines as $pip) {
            $events->push([
                'date' => $pip->expected_close_date->format('Y-m-d'),
                'title' => "{$pip->code} — {$pip->title}",
                'subtitle' => $pip->customer?->company ?: $pip->customer?->name,
                'type' => 'pipeline_close',
                'color' => 'info',
                'label' => 'Target Closing Pipeline',
                'meta' => 'Rp '.number_format((float) $pip->deal_value, 0, ',', '.'),
            ]);
        }
    }

    private function addActivityEvents($events, Carbon $start, Carbon $end): void
    {
        $activities = CrmActivity::query()
            ->with(['lead:id,name', 'customer:id,name,company', 'user:id,name'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('activity_date', [$start, $end])
                    ->orWhereBetween('next_action_date', [$start, $end]);
            })
            ->get(['id', 'type', 'subject', 'activity_date', 'next_action_date', 'next_action_note', 'status', 'crm_lead_id', 'crm_customer_id', 'user_id']);

        foreach ($activities as $act) {
            if ($act->activity_date && $act->activity_date->between($start, $end)) {
                $contact = $act->customer?->company ?: ($act->customer?->name ?: $act->lead?->name);
                $events->push([
                    'date' => $act->activity_date->format('Y-m-d'),
                    'title' => $act->subject,
                    'subtitle' => $contact,
                    'type' => 'activity',
                    'color' => 'secondary',
                    'label' => ucfirst($act->type),
                    'meta' => "PIC: {$act->user?->name} · {$act->status}",
                ]);
            }
            if ($act->next_action_date && $act->next_action_date->between($start, $end)) {
                $contact = $act->customer?->company ?: ($act->customer?->name ?: $act->lead?->name);
                $events->push([
                    'date' => $act->next_action_date->format('Y-m-d'),
                    'title' => $act->next_action_note ?: "Follow-up: {$act->subject}",
                    'subtitle' => $contact,
                    'type' => 'follow_up',
                    'color' => 'error',
                    'label' => 'Follow-up Reminder',
                    'meta' => "PIC: {$act->user?->name}",
                ]);
            }
        }
    }
}
