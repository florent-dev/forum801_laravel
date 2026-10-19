<?php

use App\Livewire\NewThread;
use App\Livewire\ThreadView;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\Role;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumService;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->member = userWithRole(Role::MEMBER);
    $this->forum = app(ForumService::class);
});

it('marks and unmarks the solution as author or moderator only', function () {
    $thread = $this->forum->createThread($this->member, $this->section, 'Help', 'How?');
    $answer = $this->forum->reply(userWithRole(Role::MEMBER), $thread, 'Like this');

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $thread])->call('markSolution', $answer->id)->assertForbidden();
    Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread])->call('markSolution', $answer->id);
    expect($thread->fresh()->solution_post_id)->toBe($answer->id);

    Livewire::actingAs(userWithRole(Role::MODERATOR, $this->section))->test(ThreadView::class, ['thread' => $thread])->call('markSolution', $answer->id);
    expect($thread->fresh()->solution_post_id)->toBeNull();
});

it('sets the prefix as author or moderator only', function () {
    $thread = threadIn($this->section, $this->member);

    Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread])->call('setPrefix', 'guide');
    expect($thread->fresh()->prefix)->toBe('guide');
    Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread])->call('setPrefix', 'invalid');
    expect($thread->fresh()->prefix)->toBeNull();
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $thread])->call('setPrefix', 'bug')->assertForbidden();
});

it('bookmarks posts and lists them', function () {
    $thread = threadIn($this->section);
    $post = $thread->posts()->first();
    $post->update(['body' => 'Worth keeping']);

    Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread])->call('toggleBookmark', $post->id);
    $this->actingAs($this->member)->get(route('bookmarks'))->assertSee('Worth keeping');

    Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread])->call('toggleBookmark', $post->id);
    expect($this->member->bookmarks()->count())->toBe(0);
});

it('hides bookmarks of posts that became private', function () {
    $post = threadIn(Section::factory()->private()->create())->posts()->first();
    $post->update(['body' => 'Now private']);
    $this->member->bookmarks()->attach($post->id, ['created_at' => now()]);

    $this->actingAs($this->member)->get(route('bookmarks'))->assertDontSee('Now private');
});

it('keeps an edit history visible to the author and moderators', function () {
    $thread = threadIn($this->section, $this->member);
    $post = $thread->posts()->first();
    $post->update(['body' => 'First version']);
    $this->forum->editPost($this->member, $post, 'Second version');

    expect($post->revisions()->first()->body)->toBe('First version');
    Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread])->call('showHistory', $post->id)->assertSee('First version');
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $thread])->call('showHistory', $post->id)->assertForbidden();
});

it('does not store a revision when nothing changed', function () {
    $post = threadIn($this->section, $this->member)->posts()->first();

    $this->forum->editPost($this->member, $post, $post->body);

    expect($post->revisions()->count())->toBe(0);
});

it('gives rank titles by post count', function () {
    expect(User::rankFor(0))->toBe('Newcomer')
        ->and(User::rankFor(10))->toBe('Apprentice')
        ->and(User::rankFor(250))->toBe('Regular')
        ->and(User::rankFor(5000))->toBe('Legend');
});

it('serves valid rss without private threads', function () {
    $this->forum->createThread($this->member, $this->section, 'Public news', 'Hello');
    $this->forum->createThread(userWithRole(Role::ADMINISTRATOR), Section::factory()->private()->create(), 'Private plan', 'Hidden');

    $xml = $this->get(route('feeds.latest'))->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->getContent();
    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('Public news')->not->toContain('Private plan');
    $this->get(route('feeds.section', $this->section))->assertOk()->assertSee('Public news');
});

it('suggests nicknames for mentions to members only', function () {
    User::factory()->create(['name' => 'cheesefan']);
    User::factory()->create(['name' => 'cheeseking']);
    User::factory()->create(['name' => 'other_100%']);

    $this->getJson(route('api.users', ['q' => 'chee']))->assertUnauthorized();
    $names = collect($this->actingAs($this->member)->getJson(route('api.users', ['q' => 'chee']))->assertOk()->json())->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['cheesefan', 'cheeseking']);
    expect($this->actingAs($this->member)->getJson(route('api.users', ['q' => '%']))->json())->toBe([]);
});
