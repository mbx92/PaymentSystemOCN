<?php

namespace App\Services;

use App\Models\CmsAccessLog;
use Illuminate\Http\Request;

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
