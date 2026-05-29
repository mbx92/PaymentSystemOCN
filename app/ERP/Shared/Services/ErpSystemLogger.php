<?php

namespace App\ERP\Shared\Services;

use App\Models\ErpSystemLog;
use Throwable;

class ErpSystemLogger
{
    public function info(string $event, string $message, array $context = []): void
    {
        $this->write('info', $event, $message, $context);
    }

    public function warning(string $event, string $message, array $context = []): void
    {
        $this->write('warning', $event, $message, $context);
    }

    public function error(string $event, string $message, array $context = []): void
    {
        $this->write('error', $event, $message, $context);
    }

    public function exception(Throwable $exception, array $context = []): void
    {
        $this->write('error', 'system.exception', $exception->getMessage(), [
            ...$context,
            'exception' => [
                'class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->take(8)->values()->all(),
            ],
        ]);
    }

    private function write(string $level, string $event, ?string $message, array $context = []): void
    {
        try {
            ErpSystemLog::query()->create([
                'channel' => $context['channel'] ?? 'erp',
                'level' => $level,
                'event' => $event,
                'message' => $message,
                'user_id' => $context['user_id'] ?? null,
                'ip_address' => $context['ip_address'] ?? null,
                'method' => $context['method'] ?? null,
                'path' => $context['path'] ?? null,
                'status_code' => $context['status_code'] ?? null,
                'context' => $context,
            ]);
        } catch (Throwable) {
            // Avoid recursive logging failures.
        }
    }
}
