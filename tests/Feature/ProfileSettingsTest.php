<?php

use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\ForumService;

it('lists public posts and threads on a profile', function () {
    $user = userWithRole(Role::MEMBER);
    $forum = app(ForumService::class);
    $forum->createThread($user, Section::factory()->create(), 'Visible thread', 'Visible body');
    $private = $forum->createThread(userWithRole(Role::ADMINISTRATOR), Section::factory()->private()->create(), 'Staff stuff', 'x');
    \App\Models\Post::factory()->create(['thread_id' => $private->id, 'user_id' => $user->id, 'body' => 'Private body']);

    $this->get(route('profile', $user))->assertOk()->assertSee('Visible body')->assertDontSee('Private body');
    $this->get(route('profile', [$user, 'tab' => 'threads']))->assertOk()->assertSee('Visible thread');
});

it('hides hidden posts from profiles', function () {
    $user = userWithRole(Role::MEMBER);
    $thread = threadIn(Section::factory()->create());
    \App\Models\Post::factory()->create(['thread_id' => $thread->id, 'user_id' => $user->id, 'body' => 'Moderated text', 'is_hidden' => true]);

    $this->get(route('profile', $user))->assertDontSee('Moderated text');
});

it('only accepts https avatar urls', function () {
    $user = userWithRole(Role::MEMBER);

    $this->actingAs($user)->put(route('settings.update'), ['avatar_url' => 'http://insecure.test/a.png'])->assertSessionHasErrors('avatar_url');
    $this->actingAs($user)->put(route('settings.update'), ['avatar_url' => 'javascript:alert(1)'])->assertSessionHasErrors('avatar_url');
    $this->actingAs($user)->put(route('settings.update'), ['avatar_url' => 'https://img.test/a.png', 'bio' => 'Hi', 'signature' => 'Sig'])->assertSessionHasNoErrors();

    expect($user->fresh()->avatar())->toBe('https://img.test/a.png')->and($user->fresh()->signature)->toBe('Sig');
});

it('generates a local avatar by default', function () {
    expect(User::factory()->make(['name' => 'zed'])->avatar())->toStartWith('data:image/svg+xml;base64,');
});

it('needs the current password to change the email', function () {
    $user = User::factory()->create(['password' => 'the-password']);

    $this->actingAs($user)->put(route('settings.email'), ['email' => 'new@test.dev', 'current_password' => 'wrong'])->assertSessionHasErrorsIn('email', 'current_password');
    $this->actingAs($user)->put(route('settings.email'), ['email' => 'new@test.dev', 'current_password' => 'the-password'])->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBe('new@test.dev');
});

it('refuses a wrong current password when changing the password', function () {
    $user = User::factory()->create(['password' => 'the-password']);

    $this->actingAs($user)->put(route('settings.password'), [
        'current_password' => 'nope', 'password' => 'another-password', 'password_confirmation' => 'another-password',
    ])->assertSessionHasErrorsIn('password', 'current_password');
});

it('shows the online status of recently active users', function () {
    $online = User::factory()->create(['name' => 'onlinemouse', 'last_seen_at' => now()]);
    User::factory()->create(['name' => 'sleepymouse', 'last_seen_at' => now()->subHour()]);

    expect($online->isOnline())->toBeTrue();
    expect($this->get(route('online'))->viewData('users')->pluck('name')->all())->toBe(['onlinemouse']);
});

it('updates last seen on requests', function () {
    $user = User::factory()->create(['last_seen_at' => null]);

    $this->actingAs($user)->get('/');

    expect($user->fresh()->last_seen_at)->not->toBeNull();
});
