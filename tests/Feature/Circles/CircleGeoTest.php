<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\User;
use App\Services\IpGeolocationService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

it('geolocates a public IP into rough coordinates', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'lat' => 51.5074,
            'lon' => -0.1278,
            'city' => 'London',
            'regionName' => 'England',
            'country' => 'United Kingdom',
        ]),
    ]);

    $geo = app(IpGeolocationService::class)->locate('8.8.8.8');

    expect($geo)->not->toBeNull()
        ->and($geo['latitude'])->toBe(51.5074)
        ->and($geo['longitude'])->toBe(-0.1278)
        ->and($geo['city'])->toBe('London');
});

it('returns null when the geolocation provider fails', function () {
    Http::fake(['ip-api.com/*' => Http::response(['status' => 'fail'], 200)]);

    expect(app(IpGeolocationService::class)->locate('8.8.8.8'))->toBeNull();
});

it('returns null for private or loopback addresses without calling out', function () {
    Http::fake();

    expect(app(IpGeolocationService::class)->locate('127.0.0.1'))->toBeNull()
        ->and(app(IpGeolocationService::class)->locate('192.168.1.10'))->toBeNull();

    Http::assertNothingSent();
});

it('includes coordinates on map nodes and my_location', function () {
    $user = User::factory()->create();
    $user->personalCircle->update(['latitude' => 51.5, 'longitude' => -0.12, 'location_label' => 'London, England']);

    Sanctum::actingAs($user);

    $this->getJson('/api/circles/map')
        ->assertOk()
        ->assertJsonPath('data.my_location.latitude', 51.5)
        ->assertJsonPath('data.nodes.0.latitude', 51.5);
});

it('updates the personal circle location when the request IP changes', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'lat' => 40.7128,
            'lon' => -74.0060,
            'city' => 'New York',
            'regionName' => 'New York',
            'country' => 'United States',
        ]),
    ]);

    $user = User::factory()->create();
    $circle = $user->personalCircle;
    $circle->update(['latitude' => 51.5, 'longitude' => -0.12, 'geo_ip' => '1.2.3.4']);

    Sanctum::actingAs($user);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->getJson('/api/circles/map')
        ->assertOk();

    $circle->refresh();

    expect((float) $circle->latitude)->toBe(40.7128)
        ->and((float) $circle->longitude)->toBe(-74.0060)
        ->and($circle->geo_ip)->toBe('8.8.8.8');
});

it('does not re-geolocate when the request IP has not changed', function () {
    Http::fake();

    $user = User::factory()->create();
    $circle = $user->personalCircle;
    $circle->update(['latitude' => 51.5, 'longitude' => -0.12, 'geo_ip' => '8.8.8.8']);

    Sanctum::actingAs($user);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->getJson('/api/circles/map')
        ->assertOk();

    Http::assertNothingSent();
});
