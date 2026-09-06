<?php

declare(strict_types=1);

use App\Mail\VerifyEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

it('blocks unverified users from protected api routes', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    Sanctum::actingAs($user);

    $this->getJson('/api/sessions')->assertStatus(403);
});

it('allows verified users to access protected api routes', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/sessions')->assertOk();
});

it('redirects unverified web users to the verification notice', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)->get('/')->assertRedirect(route('verification.notice'));
});

it('verifies a user via the signed link', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('rejects an invalid verification hash', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->actingAs($user)->get($url)->assertStatus(403);

    expect($user->refresh()->email_verified_at)->toBeNull();
});

it('resends the verification email', function () {
    Mail::fake();

    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'));

    Mail::assertSent(VerifyEmail::class);
});
