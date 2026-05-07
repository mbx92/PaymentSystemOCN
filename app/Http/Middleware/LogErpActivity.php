<?php

namespace App\Http\Middleware;

use App\ERP\Shared\Services\ErpSystemLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogErpActivity
{
    public function __construct(private readonly ErpSystemLogger $logger) {}

    private function shouldTrack(Request $request): bool
    {
        if ($request->is('erp/admin/system-logs*')) {
            return false;
        }

        return $request->is([
            'erp/*',
            'kas-*',
            'projects*',
            'project-payments*',
            'team-distribution*',
            'referrals*',
            'laporan/*',
            'export/*',
            '/',
        ]);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldTrack($request)) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->logger->exception($e, [
                'channel' => 'errors',
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'query' => $request->query(),
            ]);

            throw $e;
        }

        $statusCode = $response->getStatusCode();
        $level = $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'warning' : 'info');

        $payload = [
            'channel' => 'activity',
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'route_name' => $request->route()?->getName(),
            'query' => $request->query(),
        ];

        $message = sprintf('%s %s [%s]', $request->method(), $request->path(), $statusCode);

        if ($level === 'error') {
            $this->logger->error('activity.http', $message, $payload);
        } elseif ($level === 'warning') {
            $this->logger->warning('activity.http', $message, $payload);
        } else {
            $this->logger->info('activity.http', $message, $payload);
        }

        return $response;
    }
}

