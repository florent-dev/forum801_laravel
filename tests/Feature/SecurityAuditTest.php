<?php

use App\Livewire\Admin\Sections;
use App\Livewire\AuthModal;
use App\Livewire\NewThread;
use App\Livewire\ThreadView;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\ForumService;
use App\Services\GuildService;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('locks the edit history target against client tampering', function () {
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => threadIn(Section::factory()->create())])
        ->set('historyPostId', 1);
})->throws(CannotUpdateLockedPropertyException::class);

it('only redirects back to this site after logging in', function () {
    User::factory()->create(['name' => 'redirected', 'password' => 'the-password']);

    Livewire::withHeaders(['Referer' => 'https://evil.example.com/phish'])->test(AuthModal::class)
        ->set('name', 'redirected')->set('password', 'the-password')->call('login')
        ->assertRedirect(route('home'));
});

it('refuses to delete a section that still holds threads', function () {
    $section = Section::factory()->create();
    $thread = threadIn($section);
    $thread->delete();

    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(Sections::class)->call('delete', $section->id);

    expect(Section::find($section->id))->not->toBeNull()
        ->and(\App\Models\Thread::withTrashed()->find($thread->id))->not->toBeNull();
});

it('lets only members write in a public guild forum', function () {
    $owner = userWithRole(Role::MEMBER);
    $guild = app(GuildService::class)->create($owner, ['name' => 'Open Book', 'tag' => 'obk', 'community' => 'en']);
    $guild->update(['forum_public' => true]);
    $thread = app(ForumService::class)->createThread($owner->fresh(), $guild->section, 'Visible', 'Hello');
    $stranger = userWithRole(Role::MEMBER);

    $this->actingAs($stranger)->get(route('sections.show', $guild->section))->assertOk();
    Livewire::actingAs($stranger)->test(NewThread::class, ['section' => $guild->section])->assertForbidden();
    Livewire::actingAs($stranger)->test(ThreadView::class, ['thread' => $thread])->set('replyBody', 'Intruder')->call('reply')->assertForbidden();
    Livewire::actingAs($owner->fresh())->test(ThreadView::class, ['thread' => $thread])->set('replyBody', 'Member reply')->call('reply')->assertHasNoErrors();
});

it('sends security headers', function () {
    $this->get('/')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('never exposes emails, ips or password hashes in serialized users', function () {
    $user = User::factory()->create(['last_ip' => '1.2.3.4', 'registration_ip' => '1.2.3.4']);

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token', 'last_ip', 'registration_ip']);
});

it('rejects emails carrying header injection characters', function () {
    $this->post('/register', ['name' => 'crlfuser', 'email' => "a@example.com\r\nBcc: victim@example.com", 'password' => 'long-password-1', 'password_confirmation' => 'long-password-1'])
        ->assertSessionHasErrors('email');
    Livewire::test(AuthModal::class)->set('regName', 'crlf2')->set('regEmail', "b@example.com\nCc: x@example.com")
        ->set('regPassword', 'long-password-1')->set('regPassword_confirmation', 'long-password-1')->call('register')
        ->assertHasErrors('regEmail');
});
