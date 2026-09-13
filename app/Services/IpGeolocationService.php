<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Best-effort, city-level IP geolocation. Falls back to null on any failure
 * (the app must never break because the geolocation provider is unreachable).
 */
class IpGeolocationService
{
    public function locate(?string $ip = null): ?array
    {
        if (! config('courtly.geo.enabled', false)) {
            return null;
        }

        $ip = $ip ?: request()->ip();

        if ($this->isNonPublic($ip)) {
            return null;
        }

        $url = rtrim((string) config('courtly.geo.base_url', 'http://ip-api.com/json'), '/')
            .'/'.rawurlencode($ip)
            .'?fields=status,lat,lon,city,regionName,country';

        try {
            $response = Http::timeout((int) config('courtly.geo.timeout_seconds', 3))
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            if (! is_array($json) || ($json['status'] ?? null) !== 'success') {
                return null;
            }

            return [
                'latitude' => (float) $json['lat'],
                'longitude' => (float) $json['lon'],
                'city' => $json['city'] ?? null,
                'region' => $json['regionName'] ?? null,
                'country' => $json['country'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Skip private, loopback and reserved ranges — they cannot be geolocated.
     */
    private function isNonPublic(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
