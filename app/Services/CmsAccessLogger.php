<?php

namespace App\Services;

use App\Models\CmsAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    /**
     * @param  array<string, mixed>  $eventMeta
     */
    public static function logLandingEvent(Request $request, int $landingSiteId, string $eventName, array $eventMeta = []): void
    {
        self::insert(
            $request,
            CmsAccessLog::KIND_LANDING_PUBLIC,
            $landingSiteId,
            $request->user()?->id,
            $eventName,
            $eventMeta,
        );
    }

    public static function logCmsAdmin(Request $request): void
    {
        self::insert(
            $request,
            CmsAccessLog::KIND_CMS_ADMIN,
            null,
            $request->user()?->id,
            null,
            [],
        );
    }

    /**
     * @param  array<string, mixed>  $eventMeta
     */
    private static function insert(Request $request, string $kind, ?int $landingSiteId, ?int $userId, ?string $eventName, array $eventMeta): void
    {
        $ip = self::clientIp($request);
        $ua = $request->userAgent();
        $parsed = self::parseUserAgent($ua);
        $geo = self::lookupGeo($ip);

        try {
            $path = '/'.ltrim($request->path(), '/');
            if ($path === '//') {
                $path = '/';
            }

            CmsAccessLog::query()->create([
                'kind' => $kind,
                'landing_site_id' => $landingSiteId,
                'user_id' => $userId,
                'path' => $path,
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'ip_address' => $ip ?? '',
                'user_agent' => $ua,
                'country_code' => $geo['country_code'] ?? null,
                'country_name' => $geo['country_name'] ?? null,
                'region_name' => $geo['region_name'] ?? null,
                'city' => $geo['city'] ?? null,
                'device_type' => $parsed['device_type'],
                'browser' => $parsed['browser'],
                'os' => $parsed['os'],
                'referrer' => self::truncateReferrer($request->header('referer')),
                'event_name' => $eventName,
                'event_meta' => self::sanitizeEventMeta($eventMeta),
            ]);
        } catch (\Throwable $e) {
            Log::warning('cms_access_log_failed', ['message' => $e->getMessage()]);
        }
    }

    private static function clientIp(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip !== null ? (string) $ip : null;
    }

    /**
     * @return array{country_code: ?string, country_name: ?string, region_name: ?string, city: ?string}
     */
    private static function lookupGeo(?string $ip): array
    {
        $empty = ['country_code' => null, 'country_name' => null, 'region_name' => null, 'city' => null];
        if ($ip === null || $ip === '' || ! config('cms.access_log_geo_lookup', true)) {
            return $empty;
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $empty;
        }

        $cacheKey = 'cms_geoip:'.hash('sha256', $ip);

        return Cache::remember($cacheKey, 86400, function () use ($ip, $empty) {
            try {
                $response = Http::timeout(1.5)
                    ->get('http://ip-api.com/json/'.$ip, [
                        'fields' => 'status,country,countryCode,regionName,city',
                    ]);
                if (! $response->successful()) {
                    return $empty;
                }
                $data = $response->json();
                if (($data['status'] ?? '') !== 'success') {
                    return $empty;
                }

                return [
                    'country_code' => isset($data['countryCode']) ? substr((string) $data['countryCode'], 0, 2) : null,
                    'country_name' => isset($data['country']) ? substr((string) $data['country'], 0, 120) : null,
                    'region_name' => isset($data['regionName']) ? substr((string) $data['regionName'], 0, 120) : null,
                    'city' => isset($data['city']) ? substr((string) $data['city'], 0, 120) : null,
                ];
            } catch (\Throwable) {
                return $empty;
            }
        });
    }

    /**
     * @return array{device_type: string, browser: ?string, os: ?string}
     */
    public static function parseUserAgent(?string $ua): array
    {
        if ($ua === null || trim($ua) === '') {
            return ['device_type' => 'unknown', 'browser' => null, 'os' => null];
        }
        $l = strtolower($ua);
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|lighthouse/i', $l)) {
            return ['device_type' => 'bot', 'browser' => 'Bot', 'os' => self::guessOs($l)];
        }
        $device = 'desktop';
        if (preg_match('/ipad|tablet|playbook|silk|(android(?!.*mobile))/i', $l)) {
            $device = 'tablet';
        } elseif (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile/i', $l)) {
            $device = 'mobile';
        }

        return [
            'device_type' => $device,
            'browser' => self::guessBrowser($l),
            'os' => self::guessOs($l),
        ];
    }

    private static function guessBrowser(string $l): ?string
    {
        if (str_contains($l, 'edg/') || str_contains($l, 'edga/') || str_contains($l, 'edgios')) {
            return 'Edge';
        }
        if (str_contains($l, 'opr/') || str_contains($l, 'opera')) {
            return 'Opera';
        }
        if (str_contains($l, 'chrome/') && ! str_contains($l, 'chromium')) {
            return 'Chrome';
        }
        if (str_contains($l, 'firefox/') || str_contains($l, 'fxios')) {
            return 'Firefox';
        }
        if (str_contains($l, 'safari/') && ! str_contains($l, 'chrome')) {
            return 'Safari';
        }

        return null;
    }

    private static function guessOs(string $l): ?string
    {
        if (str_contains($l, 'windows')) {
            return 'Windows';
        }
        if (str_contains($l, 'mac os x') || str_contains($l, 'iphone') || str_contains($l, 'ipad')) {
            return str_contains($l, 'iphone') || str_contains($l, 'ipad') ? 'iOS' : 'macOS';
        }
        if (str_contains($l, 'android')) {
            return 'Android';
        }
        if (str_contains($l, 'linux')) {
            return 'Linux';
        }

        return null;
    }

    private static function truncateReferrer(?string $ref): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }
        if (strlen($ref) > 2000) {
            return substr($ref, 0, 2000);
        }

        return $ref;
    }

    /**
     * @param  array<string, mixed>  $meta
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
