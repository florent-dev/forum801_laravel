<?php

use App\Livewire\HeaderBar;
use App\Models\Report;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Notifications\ForumActivity;
use App\Services\ForumService;
use App\Services\MessageService;
use Livewire\Livewire;

it('counts unread threads, messages, notifications and reports', function () {
    $section = Section::factory()->create();
    $user = userWithRole(Role::SENIOR_MODERATOR);
    $forum = app(ForumService::class);
    $thread = $forum->createThread($user, $section, 'Mine', 'Hi');
    $forum->reply(userWithRole(Role::MEMBER), $thread, 'News');
    app(MessageService::class)->start(userWithRole(Role::MEMBER), collect([$user]), 'PM', 'Hello');
    Report::create(['post_id' => $thread->posts()->first()->id, 'reporter_id' => userWithRole(Role::MEMBER)->id, 'reason' => 'x']);

    Livewire::actingAs($user)->test(HeaderBar::class)
        ->assertViewHas('unread', 1)
        ->assertViewHas('messages', 1)
        ->assertViewHas('notificationCount', 2)
        ->assertViewHas('reports', 1);
});

it('hides the reports counter from members', function () {
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(HeaderBar::class)->assertViewHas('reports', null);
});

it('marks all notifications read', function () {
    $user = userWithRole(Role::MEMBER);
    $user->notify(new ForumActivity('reply', 'x', [], url('/')));

    Livewire::actingAs($user)->test(HeaderBar::class)->call('markAllNotificationsRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('logs in and out with the classic forms', function () {
    User::factory()->create(['name' => 'classic', 'password' => 'classic-password']);

    $this->post('/login', ['name' => 'classic', 'password' => 'classic-password'])->assertRedirect(route('home'));
    $this->assertAuthenticated();
    $this->post('/logout')->assertRedirect(route('home'));
    $this->assertGuest();
});

it('throttles login attempts', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['name' => 'victim', 'password' => 'wrong'.$i]);
    }

    $this->post('/login', ['name' => 'victim', 'password' => 'wrong'])->assertStatus(429);
});

it('throttles login attempts in the modal', function () {
    $component = Livewire::test(\App\Livewire\AuthModal::class)->set('name', 'victim2');
    foreach (range(1, 5) as $i) {
        $component->set('password', 'wrong'.$i)->call('login');
    }

    $component->set('password', 'wrong')->call('login')->assertHasErrors('name');
    expect(collect($component->errors()->get('name'))->implode(' '))->toContain('Too many attempts');
});

it('validates registration', function () {
    User::factory()->create(['name' => 'taken', 'email' => 'taken@test.dev']);

    $this->post('/register', ['name' => 'taken', 'email' => 'taken@test.dev', 'password' => 'short', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors(['name', 'email', 'password']);
    $this->post('/register', ['name' => 'bad name!', 'email' => 'ok@test.dev', 'password' => 'long-enough-pw', 'password_confirmation' => 'long-enough-pw'])
        ->assertSessionHasErrors('name');
});

it('hashes passwords', function () {
    $user = User::factory()->create(['password' => 'plain-text']);

    expect($user->password)->not->toBe('plain-text')->and(\Hash::check('plain-text', $user->password))->toBeTrue();
});

it('shows a suspension banner to banned users', function () {
    $user = sanction(User::factory()->create(), 'suspension', 'Flooding');

    $this->actingAs($user)->get('/')->assertSee('Your account is suspended')->assertSee('Flooding');
});

it('requires csrf tokens on forms', function () {
    $this->withMiddleware()->app->instance('env', 'production');
    $response = $this->call('POST', '/logout');

    expect($response->status())->toBeIn([419, 302]);
})->skip('Laravel disables CSRF checks while running tests; covered by the framework.');
