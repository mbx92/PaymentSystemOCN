<?php

namespace App\Services;

use App\Models\CmsAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CmsAccessLogger
{
    public static function logLandingPublic(Request $request, int $landingSiteId): void
    {
        self::insert(
            $request,
            CmsAccessLog::KIND_LANDING_PUBLIC,
            $landingSiteId,
            $request->user()?->id,
            CmsAccessLog::EVENT_PAGE_VIEW,
            [
                'host' => $request->getHost(),
                'query' => $request->getQueryString(),
            ],
        );
    }

    public static function logCmsAdmin(Request $request): void
    {
        self::insert(
            $request,
            CmsAccessLog::KIND_CMS_ADMIN,
            Auth::id(),
            Auth::id(),
            CmsAccessLog::EVENT_PAGE_VIEW,
            [
                'route' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function logLandingEvent(Request $request, int $landingSiteId, string $eventName, array $meta = []): void
    {
        self::insert(
            $request,
            CmsAccessLog::KIND_LANDING_PUBLIC,
            $landingSiteId,
            $request->user()?->id,
            $eventName,
            $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function insert(Request $request, string $kind, ?int $landingSiteId, ?int $userId, string $event, array $meta): void
    {
        try {
            $config = config('cms-access-log');
            if (($config['enabled'] ?? true) === false) {
                return;
            }

            CmsAccessLog::query()->create([
                'kind' => $kind,
                'landing_site_id' => $landingSiteId,
                'user_id' => $userId,
                'path' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'ip_address' => self::anonymizeIp($request->ip() ?? '0.0.0.0'),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'referrer' => mb_substr((string) $request->header('referer'), 0, 500),
                'device_type' => self::detectDeviceType((string) $request->userAgent()),
                'browser' => mb_substr((string) $request->header('sec-ch-ua'), 0, 100),
                'event_name' => $event,
                'event_meta' => self::sanitizeEventMeta($meta),
            ]);
        } catch (\Throwable $e) {
            Log::warning('CmsAccessLogger insert failed: '.$e->getMessage());
        }
    }

    private static function detectDeviceType(string $ua): string
    {
        if ($ua === '') {
            return 'unknown';
        }
        if (preg_match('/mobile|android|iphone|ipad|ipod/i', $ua)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad/i', $ua)) {
            return 'tablet';
        }

        return 'desktop';
    }

    private static function anonymizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return $parts[0].'.'.$parts[1].'.'.$parts[2].'.0';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts[count($parts) - 1] = '0';

            return implode(':', $parts);
        }

        return $ip;
    }

    /**
     * @return array<string, mixed>
     */
    private static function sanitizeEventMeta(array $meta): array
    {
        $out = [];

        foreach ($meta as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }
                $out[$key] = mb_substr($trimmed, 0, 500);

                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $out[$key] = $value;

                continue;
            }

            if (is_array($value)) {
                $nested = self::sanitizeEventMeta($value);
                if ($nested !== []) {
                    $out[$key] = $nested;
                }
            }
        }

        return $out;
    }
}
