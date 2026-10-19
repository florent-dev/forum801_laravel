<?php

use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;

it('makes an existing account administrator', function () {
    $user = userWithRole(Role::MEMBER);

    $this->artisan('forum:make-admin', ['name' => strtoupper($user->name)])
        ->expectsOutput("{$user->name} is now an administrator.")
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue()
        ->and(SecurityEvent::where('type', 'admin_granted')->where('user_id', $user->id)->exists())->toBeTrue();
    $this->actingAs($user->fresh())->get(route('admin.users'))->assertOk();
});

it('fails for unknown accounts', function () {
    $this->artisan('forum:make-admin', ['name' => 'nobody'])->assertFailed();
});

it('does nothing when the account is already administrator', function () {
    $admin = userWithRole(Role::ADMINISTRATOR);

    $this->artisan('forum:make-admin', ['name' => $admin->name])->expectsOutput("{$admin->name} is already an administrator.")->assertSuccessful();

    expect($admin->roles()->where('name', Role::ADMINISTRATOR)->count())->toBe(1);
});

it('can confirm the email address', function () {
    $user = User::factory()->unverified()->create();

    $this->artisan('forum:make-admin', ['name' => $user->name, '--verify-email' => true])->assertSuccessful();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->hasRole(Role::MEMBER))->toBeTrue();
});

it('removes the role but never from the last administrator', function () {
    $first = userWithRole(Role::ADMINISTRATOR);

    $this->artisan('forum:make-admin', ['name' => $first->name, '--remove' => true])->assertFailed();
    expect($first->fresh()->isAdmin())->toBeTrue();

    userWithRole(Role::ADMINISTRATOR);
    $this->artisan('forum:make-admin', ['name' => $first->name, '--remove' => true])->assertSuccessful();
    expect($first->fresh()->isAdmin())->toBeFalse();
});
