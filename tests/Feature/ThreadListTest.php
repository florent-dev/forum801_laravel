<?php

use App\Livewire\ThreadList;
use App\Models\Role;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumService;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
});

it('shows the last poster and not the thread creator', function () {
    $creator = User::factory()->create(['name' => 'thecreator']);
    $replier = User::factory()->create(['name' => 'lastreplier']);
    $forum = app(ForumService::class);
    $thread = $forum->createThread($creator, $this->section, 'Who is shown', 'Hi');
    $forum->reply($replier, $thread, 'Reply');

    Livewire::test(ThreadList::class, ['section' => $this->section])
        ->assertSee('lastreplier')
        ->assertDontSee('thecreator');
});

it('lists pinned threads first', function () {
    $old = Thread::factory()->create(['section_id' => $this->section->id, 'title' => 'Pinned oldie', 'is_pinned' => true, 'last_post_at' => now()->subYear()]);
    $new = Thread::factory()->create(['section_id' => $this->section->id, 'title' => 'Fresh topic', 'last_post_at' => now()]);

    Livewire::test(ThreadList::class, ['section' => $this->section])->assertSeeInOrder(['Pinned oldie', 'Fresh topic']);
});

it('paginates threads by 25', function () {
    Thread::factory()->count(30)->create(['section_id' => $this->section->id]);

    Livewire::test(ThreadList::class, ['section' => $this->section])
        ->assertSee('1 / 2')
        ->call('nextPage', 'tp')
        ->assertSee('2 / 2');
});

it('falls back to activity sorting for unknown values', function () {
    Livewire::test(ThreadList::class, ['section' => $this->section])
        ->set('sort', 'hacker')
        ->assertSet('sort', 'activity');
});

it('sorts by newest and by views', function () {
    Thread::factory()->create(['section_id' => $this->section->id, 'title' => 'Old but viewed', 'created_at' => now()->subDays(5), 'views_count' => 500]);
    Thread::factory()->create(['section_id' => $this->section->id, 'title' => 'Brand new', 'created_at' => now(), 'views_count' => 1]);

    Livewire::test(ThreadList::class, ['section' => $this->section])->set('sort', 'newest')->assertSeeInOrder(['Brand new', 'Old but viewed']);
    Livewire::test(ThreadList::class, ['section' => $this->section])->set('sort', 'views')->assertSeeInOrder(['Old but viewed', 'Brand new']);
});

it('flags threads with new posts without a jump link', function () {
    $user = userWithRole(Role::MEMBER);
    $forum = app(ForumService::class);
    $thread = $forum->createThread(userWithRole(Role::MEMBER), $this->section, 'Has news', 'Hi');
    $forum->markRead($user, $thread, $thread->last_post_id);
    $forum->reply(userWithRole(Role::MEMBER), $thread, 'News!');

    Livewire::actingAs($user)->test(ThreadList::class, ['section' => $this->section])
        ->assertDontSee('first unread')
        ->assertSeeHtml('unread-dot');
});

it('refuses to list a private section to outsiders', function () {
    $private = Section::factory()->private()->create();

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadList::class, ['section' => $private])->assertForbidden();
});

it('only lets members mark sections read', function () {
    Livewire::test(ThreadList::class, ['section' => $this->section])->call('markSectionRead')->assertForbidden();
});

it('opens the list on the page that contains the active thread', function () {
    $threads = Thread::factory()->count(30)->sequence(fn ($s) => ['last_post_at' => now()->subMinutes($s->index)])->create(['section_id' => $this->section->id]);
    $deep = $threads->last();

    Livewire::test(ThreadList::class, ['section' => $this->section, 'activeThreadId' => $deep->id])
        ->assertSee('2 / 2')
        ->assertSee($deep->title);
});

it('keeps the list page and sort in thread links', function () {
    Thread::factory()->count(30)->create(['section_id' => $this->section->id]);

    Livewire::test(ThreadList::class, ['section' => $this->section])
        ->set('sort', 'views')
        ->call('nextPage', 'tp')
        ->assertSeeHtml('tp=2')
        ->assertSeeHtml('sort=views');
});

it('lets moderators bulk delete and restore threads', function () {
    $threads = Thread::factory()->count(3)->create(['section_id' => $this->section->id]);
    $mod = userWithRole(Role::MODERATOR, $this->section);

    Livewire::actingAs($mod)->test(ThreadList::class, ['section' => $this->section])
        ->call('toggleSelectMode')
        ->set('selected', $threads->take(2)->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('bulk', 'delete');

    expect(Thread::count())->toBe(1)
        ->and(Thread::onlyTrashed()->count())->toBe(2)
        ->and(Thread::onlyTrashed()->first()->deleted_by)->toBe($mod->id);

    Livewire::actingAs($mod)->test(ThreadList::class, ['section' => $this->section])
        ->call('toggleShowDeleted')
        ->assertSee($threads->first()->title)
        ->call('toggleSelectMode')
        ->set('selected', [(string) $threads->first()->id])
        ->call('bulk', 'restore');

    expect(Thread::count())->toBe(2);
});

it('bulk locks and pins threads', function () {
    $threads = Thread::factory()->count(2)->create(['section_id' => $this->section->id]);
    $component = Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(ThreadList::class, ['section' => $this->section])
        ->call('toggleSelectMode');

    $component->set('selected', $threads->pluck('id')->map(fn ($id) => (string) $id)->all())->call('bulk', 'lock');
    $component->set('selected', $threads->pluck('id')->map(fn ($id) => (string) $id)->all())->call('bulk', 'pin');

    expect(Thread::where('is_locked', true)->where('is_pinned', true)->count())->toBe(2);
});

it('forbids bulk actions to members and unknown actions', function () {
    $thread = Thread::factory()->create(['section_id' => $this->section->id]);

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadList::class, ['section' => $this->section])->call('toggleSelectMode')->assertForbidden();
    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(ThreadList::class, ['section' => $this->section])
        ->set('selected', [(string) $thread->id])->call('bulk', 'explode')->assertStatus(422);
});

it('ignores threads of other sections in bulk actions', function () {
    $foreign = Thread::factory()->create();

    Livewire::actingAs(userWithRole(Role::MODERATOR, $this->section))->test(ThreadList::class, ['section' => $this->section])
        ->set('selected', [(string) $foreign->id])->call('bulk', 'delete');

    expect($foreign->fresh()->trashed())->toBeFalse();
});

it('hides deleted threads from members', function () {
    $thread = Thread::factory()->create(['section_id' => $this->section->id, 'title' => 'Gone thread']);
    $thread->delete();

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadList::class, ['section' => $this->section])->assertDontSee('Gone thread');
    $this->actingAs(userWithRole(Role::MEMBER))->get(route('threads.show', [$thread->id, $thread->slug]))->assertForbidden();
    $this->actingAs(userWithRole(Role::MODERATOR, $this->section))->get(route('threads.show', [$thread->id, $thread->slug]))->assertOk()->assertSee('This thread was deleted');
});

it('colors post counters by reading state', function () {
    $user = userWithRole(Role::MEMBER);
    $forum = app(ForumService::class);
    $read = $forum->createThread(userWithRole(Role::MEMBER), $this->section, 'All read', 'x x');
    $forum->createThread(userWithRole(Role::MEMBER), $this->section, 'Never opened', 'x x');
    $news = $forum->createThread(userWithRole(Role::MEMBER), $this->section, 'Has news', 'x x');
    $forum->markRead($user, $read, $read->last_post_id);
    $forum->markRead($user, $news, $news->last_post_id);
    $forum->reply(userWithRole(Role::MEMBER), $news, 'new!');

    $html = Livewire::actingAs($user)->test(ThreadList::class, ['section' => $this->section])->html();

    expect(substr_count($html, 'count-new'))->toBe(1)->and(substr_count($html, 'count-read'))->toBe(1);
});
