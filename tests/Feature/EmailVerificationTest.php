<?php

use App\Livewire\AuthModal;
use App\Livewire\NewThread;
use App\Livewire\ThreadView;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

it('sends exactly one confirmation email on registration', function () {
    Notification::fake();

    Livewire::test(AuthModal::class)
        ->set('regName', 'mailmouse')->set('regEmail', 'mail@test.dev')
        ->set('regPassword', 'a-long-password')->set('regPassword_confirmation', 'a-long-password')
        ->call('register');

    $user = User::where('name', 'mailmouse')->first();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

it('blocks unconfirmed members from creating threads and replying', function () {
    $user = User::factory()->unverified()->create();
    $user->assignRole(Role::MEMBER);
    $section = Section::factory()->create();
    $thread = threadIn($section);

    Livewire::actingAs($user)->test(NewThread::class, ['section' => $section])->assertForbidden();
    Livewire::actingAs($user)->test(ThreadView::class, ['thread' => $thread])
        ->assertSee('Confirm your email address to reply.')
        ->set('replyBody', 'hi')->call('reply')->assertForbidden();
    $this->actingAs($user)->get('/')->assertSee('Please confirm your email address');
});

it('confirms the email with the signed link', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user)->get($url)->assertRedirect(route('home'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects tampered confirmation links', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@test.dev')]);
    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the link with a rate limit', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    foreach (range(1, 3) as $i) {
        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();
    }
    $this->actingAs($user)->post(route('verification.send'))->assertStatus(429);
    Notification::assertSentToTimes($user, VerifyEmail::class, 3);
});

it('asks to confirm again after changing the email', function () {
    Notification::fake();
    $user = User::factory()->create(['password' => 'the-password']);

    $this->actingAs($user)->put(route('settings.email'), ['email' => 'fresh@test.dev', 'current_password' => 'the-password']);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('shows the confirmation page only to unconfirmed members', function () {
    $this->actingAs(User::factory()->unverified()->create())->get(route('verification.notice'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('verification.notice'))->assertRedirect(route('home'));
});
