<?php

namespace App\Http\Middleware;

use App\ERP\Shared\Services\ErpSystemLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogErpActivity
{
    public function __construct(private readonly ErpSystemLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('erp/*') && ! $request->is('kas-*') && ! $request->is('/')) {
            return $response;
        }

        $user = $request->user();
        $statusCode = $response->getStatusCode();
        $level = $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'warning' : 'info');

        $payload = [
            'channel' => 'activity',
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'route_name' => $request->route()?->getName(),
            'query' => $request->query(),
        ];

        $message = sprintf(
            '%s %s [%s]',
            $request->method(),
            $request->path(),
            $statusCode
        );

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

