<?php

namespace App\Http\Controllers;

use App\Models\ErpSystemLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ErpSystemLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ErpSystemLog::query()->with('user');

        if ($level = $request->string('level')->toString()) {
            $query->where('level', $level);
        }

        if ($channel = $request->string('channel')->toString()) {
            $query->where('channel', $channel);
        }

        if ($event = $request->string('event')->toString()) {
            $query->where('event', 'like', '%'.$event.'%');
        }

        if ($method = $request->string('method')->toString()) {
            $query->where('method', strtoupper($method));
        }

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($inner) use ($q): void {
                $inner->where('message', 'like', '%'.$q.'%')
                    ->orWhere('path', 'like', '%'.$q.'%')
                    ->orWhere('event', 'like', '%'.$q.'%');
            });
        }

        if ($dateFrom = $request->date('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->date('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (ErpSystemLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toDateTimeString(),
                'channel' => $log->channel,
                'level' => $log->level,
                'event' => $log->event,
                'message' => $log->message,
                'user' => $log->user?->only(['id', 'name', 'email']),
                'method' => $log->method,
                'path' => $log->path,
                'status_code' => $log->status_code,
                'context' => $log->context,
            ]);

        $levels = ErpSystemLog::query()
            ->select('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level');

        $channels = ErpSystemLog::query()
            ->select('channel')
            ->distinct()
            ->orderBy('channel')
            ->pluck('channel');

        return Inertia::render('ERP/Admin/SystemLogs', [
            'logs' => $logs,
            'filters' => $this->filtersWithPerPage($request, ['level', 'channel', 'event', 'method', 'q', 'date_from', 'date_to']),
            'levels' => $levels,
            'channels' => $channels,
        ]);
    }
}
