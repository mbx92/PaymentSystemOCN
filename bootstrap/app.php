<?php

use App\ERP\Shared\Services\ErpSystemLogger;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogCmsAdminPanelAccess;
use App\Http\Middleware\LogErpActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ErpMaintenanceMode::class,
            LogErpActivity::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'log.cms.admin.access' => LogCmsAdminPanelAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $exception): void {
            $request = app()->bound('request') ? request() : null;
            app(ErpSystemLogger::class)->exception($exception, [
                'channel' => 'errors',
                'user_id' => $request?->user()?->id,
                'ip_address' => $request?->ip(),
                'method' => $request?->method(),
                'path' => $request?->path(),
            ]);
        });
    })->create();
