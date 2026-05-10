<?php

namespace App\Http\Middleware;

use App\Services\CmsAccessLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogCmsAdminPanelAccess
{
    private const ROUTE_NAMES = [
        'erp.cms',
        'erp.cms.sites',
        'erp.cms.media',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->method() !== 'GET') {
            return $response;
        }
        $name = $request->route()?->getName();
        if ($name === null || ! in_array($name, self::ROUTE_NAMES, true)) {
            return $response;
        }
        if ($request->user() === null) {
            return $response;
        }
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        CmsAccessLogger::logCmsAdmin($request);

        return $response;
    }
}
