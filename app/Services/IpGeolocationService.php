<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort, city-level IP geolocation. Falls back to null on any failure
 * (the app must never break because the geolocation provider is unreachable).
 */
class IpGeolocationService
{
    public function locate(?string $ip = null): ?array
    {
        if (! config('courtly.geo.enabled', false)) {
            Log::warning('courtly.geo: disabled by config (GEO_ENABLED)');
            return null;
        }

        $ip = $ip ?: request()->ip();

        // Local dev: `php artisan serve` (no reverse proxy) reports 127.0.0.1,
        // which can never be geolocated. GEO_TEST_IP lets you simulate a public
        // IP for testing without touching production (only applies when the
        // real request IP is non-public and the env var is set).
        $testIp = (string) config('courtly.geo.test_ip', '');
        if ($testIp !== '' && $this->isNonPublic($ip)) {
            $ip = $testIp;
        }

        if ($this->isNonPublic($ip)) {
            Log::warning('courtly.geo: skipping non-public IP', ['ip' => $ip]);
            return null;
        }

        return $this->queryIpApi($ip) ?? $this->queryIpWhois($ip);
    }

    private function queryIpApi(string $ip): ?array
    {
        $url = rtrim((string) config('courtly.geo.base_url', 'http://ip-api.com/json'), '/')
            .'/'.rawurlencode($ip)
            .'?fields=status,lat,lon,city,regionName,country';

        $json = $this->getJson($url);

        if ($json === null || ($json['status'] ?? null) !== 'success') {
            Log::warning('courtly.geo: ip-api lookup failed', ['ip' => $ip, 'body' => $json]);
            return null;
        }

        return [
            'latitude' => (float) $json['lat'],
            'longitude' => (float) $json['lon'],
            'city' => $json['city'] ?? null,
            'region' => $json['regionName'] ?? null,
            'country' => $json['country'] ?? null,
        ];
    }

    private function queryIpWhois(string $ip): ?array
    {
        $url = rtrim((string) config('courtly.geo.fallback_url', 'https://ipwho.is'), '/')
            .'/'.rawurlencode($ip)
            .'?fields=success,latitude,longitude,city,region,country';

        $json = $this->getJson($url);

        if ($json === null || ($json['success'] ?? false) !== true) {
            Log::warning('courtly.geo: ipwho.is fallback failed', ['ip' => $ip, 'body' => $json]);
            return null;
        }

        return [
            'latitude' => (float) $json['latitude'],
            'longitude' => (float) $json['longitude'],
            'city' => $json['city'] ?? null,
            'region' => $json['region'] ?? null,
            'country' => $json['country'] ?? null,
        ];
    }

    private function getJson(string $url): ?array
    {
        try {
            $response = Http::timeout((int) config('courtly.geo.timeout_seconds', 3))
                ->get($url);

            if (! $response->successful()) {
                Log::warning('courtly.geo: provider HTTP error', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('courtly.geo: provider request failed', ['url' => $url, 'error' => $e->getMessage()]);
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
