<?php

use App\Models\Role;
use App\Models\Section;
use App\Services\ForumService;
use App\Services\MessageService;

// Builds a small forum and returns the URLs worth visiting
function smokeUrls(): array
{
    $section = Section::factory()->create(['slug' => 'smoke-section']);
    $author = userWithRole(Role::MEMBER);
    $thread = app(ForumService::class)->createThread($author, $section, 'Smoke thread', 'Hello **smoke**');

    return [$section, $thread, $author];
}

it('renders every public page for guests', function () {
    [$section, $thread, $author] = smokeUrls();

    foreach (['/', route('sections.show', $section), $thread->url(), route('posts.show', $thread->posts()->first()), route('search'), route('search', ['q' => 'smoke']), route('search', ['q' => 'smoke', 'in' => 'posts']), route('profile', $author), route('profile', [$author, 'tab' => 'threads']), route('online'), route('login'), route('register')] as $url) {
        $response = $this->get($url);
        expect($response->status())->toBeIn([200, 302], "Failed on $url");
        if ($response->status() === 302) {
            $this->get($response->headers->get('Location'))->assertOk();
        }
    }
});

it('redirects guests away from member pages', function () {
    [$section] = smokeUrls();

    foreach ([route('threads.create', $section), route('unread'), route('notifications'), route('subscriptions'), route('messages'), route('settings'), route('moderation'), route('admin.users')] as $url) {
        $this->get($url)->assertRedirect('/login');
    }
});

it('renders every member page', function () {
    [$section, $thread] = smokeUrls();
    $member = userWithRole(Role::MEMBER);
    $conversation = app(MessageService::class)->start($member, collect([userWithRole(Role::MEMBER)]), 'Hi', 'Hello');

    foreach (['/', route('sections.show', $section), $thread->url(), route('threads.create', $section), route('unread'), route('threads.unread', $thread), route('notifications'), route('subscriptions'), route('messages'), route('messages', ['to' => 'someone']), route('messages.show', $conversation), route('settings'), route('settings', 'language'), route('settings', 'privacy'), route('settings', 'account'), route('friends'), route('bookmarks'), route('profile', $member), route('profile', [$member, 'tab' => 'friends'])] as $url) {
        $response = $this->actingAs($member)->get($url);
        expect($response->status())->toBeIn([200, 302], "Failed on $url");
    }
});

it('renders every staff page for an administrator', function () {
    [$section] = smokeUrls();
    $admin = userWithRole(Role::ADMINISTRATOR);

    foreach ([route('moderation'), route('moderation', ['tab' => 'logs']), route('moderation', ['tab' => 'deleted']), route('admin.users'), route('admin.sections'), route('admin.roles'), route('admin.security'), route('admin.maintenance'), route('guilds.index'), route('sections.settings', $section)] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});

it('returns 404 for unknown records', function () {
    $this->get('/s/does-not-exist')->assertNotFound();
    $this->get('/t/999999/x')->assertNotFound();
    $this->get('/p/999999')->assertNotFound();
    $this->get('/u/nobody-here')->assertNotFound();
});

it('redirects wrong thread slugs to the canonical url', function () {
    [, $thread] = smokeUrls();

    $this->get('/t/'.$thread->id.'/wrong-slug')->assertRedirect($thread->url());
    $this->get('/t/'.$thread->id)->assertRedirect($thread->url());
});

it('serves the help page and remembers the finished tour', function () {
    $this->get(route('help'))->assertOk()->assertSee('Help');
    $user = userWithRole(\App\Models\Role::MEMBER);

    $this->get('/')->assertSee('forumTour');
    $this->actingAs($user)->post(route('tour.done', 'section'))->assertNoContent();
    $this->actingAs($user)->post(route('tour.done', 'section'))->assertNoContent();
    $this->actingAs($user)->post('/tour/done/evil')->assertNotFound();

    expect($user->fresh()->tours_seen)->toBe(['section'])
        ->and($user->fresh()->hasSeenTour('main'))->toBeFalse();
});
