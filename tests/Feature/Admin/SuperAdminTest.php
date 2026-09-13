<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Circle;
use App\Models\Player;
use App\Models\Session;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

it('creates the super admin with the expected credentials', function () {
    (new SuperAdminSeeder())->run();

    $user = User::where('email', 'admin@regno.ai')->firstOrFail();

    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->isAdmin())->toBeTrue()
        ->and(Hash::check('Lynda197&', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->personalCircle)->not->toBeNull();
});

it('does not overwrite a changed password on re-seed', function () {
    (new SuperAdminSeeder())->run();
    $user = User::where('email', 'admin@regno.ai')->firstOrFail();

    $user->password = Hash::make('NewSecret123');
    $user->save();

    (new SuperAdminSeeder())->run();

    $fresh = $user->fresh();
    expect(Hash::check('NewSecret123', $fresh->password))->toBeTrue()
        ->and(Hash::check('Lynda197&', $fresh->password))->toBeFalse();
});

it('blocks non-super-admins from the admin panel', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN]);
    $this->actingAs($user);

    $this->get('/admin')->assertForbidden();
});

it('lets the super admin open the admin panel and its data', function () {
    $super = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
    $this->actingAs($super);

    $this->get('/admin')->assertOk();

    $this->getJson('/admin/data')
        ->assertOk()
        ->assertJsonStructure(['counts' => ['users', 'circles', 'sessions', 'players', 'matches']]);
});

it('lets the super admin change another user role', function () {
    $super = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
    $target = User::factory()->create(['role' => UserRole::PLAYER]);
    $this->actingAs($super);

    $this->patchJson("/admin/users/{$target->id}/role", ['role' => 'ORGANISER'])
        ->assertOk();

    expect($target->fresh()->role)->toBe(UserRole::ORGANISER);
});

it('prevents the super admin from changing their own role or deleting themselves', function () {
    $super = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
    $this->actingAs($super);

    $this->patchJson("/admin/users/{$super->id}/role", ['role' => 'PLAYER'])
        ->assertStatus(422);

    $this->deleteJson("/admin/users/{$super->id}")
        ->assertStatus(422);
});

it('lets the super admin delete users, circles, sessions and players', function () {
    $super = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
    $this->actingAs($super);

    $user = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $user->id]);
    $session = Session::factory()->create(['circle_id' => $circle->id, 'created_by' => $user->id]);
    $player = Player::factory()->create(['circle_id' => $circle->id]);

    $this->deleteJson("/admin/players/{$player->id}")->assertOk();
    $this->deleteJson("/admin/sessions/{$session->id}")->assertOk();
    $this->deleteJson("/admin/circles/{$circle->id}")->assertOk();
    $this->deleteJson("/admin/users/{$user->id}")->assertOk();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('circles', ['id' => $circle->id]);
    $this->assertDatabaseMissing('sessions', ['id' => $session->id]);
    $this->assertDatabaseMissing('players', ['id' => $player->id]);
});

it('lets the super admin change their own password', function () {
    $super = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
    $this->actingAs($super);

    $this->postJson('/admin/password', [
        'current_password' => 'password',
        'new_password' => 'MyNewPass123',
        'new_password_confirmation' => 'MyNewPass123',
    ])->assertOk();

    expect(Hash::check('MyNewPass123', $super->fresh()->password))->toBeTrue();
});

it('rejects a password change with an incorrect current password', function () {
    $super = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
    $this->actingAs($super);

    $this->postJson('/admin/password', [
        'current_password' => 'wrong-password',
        'new_password' => 'MyNewPass123',
        'new_password_confirmation' => 'MyNewPass123',
    ])->assertStatus(422);
});

it('grants a super admin access to any session and player', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $owner->id]);
    $session = Session::factory()->create(['circle_id' => $circle->id, 'created_by' => $owner->id]);
    $player = Player::factory()->create(['circle_id' => $circle->id]);

    $super = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

    expect($session->isAccessibleBy($super))->toBeTrue()
        ->and($player->isAccessibleBy($super))->toBeTrue()
        ->and($player->isManageableBy($super))->toBeTrue();
});
