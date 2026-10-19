<?php

use App\Livewire\ThreadView;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Role;
use App\Models\Section;
use App\Models\Thread;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->thread = threadIn($this->section);
});

it('paginates posts by 15', function () {
    Post::factory()->count(20)->create(['thread_id' => $this->thread->id]);

    Livewire::test(ThreadView::class, ['thread' => $this->thread])
        ->assertSee('1 / 2')
        ->call('nextPage')
        ->assertSee('2 / 2');
});

it('shows hidden posts only to their author and moderators', function () {
    $author = userWithRole(Role::MEMBER);
    Post::factory()->create(['thread_id' => $this->thread->id, 'user_id' => $author->id, 'is_hidden' => true, 'body_html' => '<p>secret words</p>']);

    Livewire::test(ThreadView::class, ['thread' => $this->thread])->assertDontSee('secret words');
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $this->thread])->assertDontSee('secret words');
    Livewire::actingAs($author)->test(ThreadView::class, ['thread' => $this->thread])->assertSee('secret words')->assertSee('Hidden by');
    Livewire::actingAs(userWithRole(Role::SENIOR_MODERATOR))->test(ThreadView::class, ['thread' => $this->thread])->assertSee('secret words');
});

it('lets moderators edit any post in their section', function () {
    $post = $this->thread->posts()->first();

    Livewire::actingAs(userWithRole(Role::MODERATOR, $this->section))
        ->test(ThreadView::class, ['thread' => $this->thread])
        ->call('startEdit', $post->id)
        ->set('editBody', 'Fixed by a moderator')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($post->fresh()->body)->toBe('Fixed by a moderator');
});

it('rejects unknown reaction types', function () {
    Livewire::actingAs(userWithRole(Role::MEMBER))
        ->test(ThreadView::class, ['thread' => $this->thread])
        ->call('react', $this->thread->posts()->first()->id, 'evil')
        ->assertStatus(422);
});

it('cannot touch posts of another thread', function () {
    $foreign = threadIn($this->section)->posts()->first();

    Livewire::actingAs(userWithRole(Role::MEMBER))
        ->test(ThreadView::class, ['thread' => $this->thread])
        ->call('react', $foreign->id, 'like');
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('sends the quoted post to the reply box', function () {
    Livewire::actingAs(userWithRole(Role::MEMBER))
        ->test(ThreadView::class, ['thread' => $this->thread])
        ->call('quote', $this->thread->posts()->first()->id)
        ->assertDispatched('quote-post');
});

it('lets the author rename its thread but not other members', function () {
    $author = $this->thread->user;

    Livewire::actingAs($author)->test(ThreadView::class, ['thread' => $this->thread])
        ->set('renameTitle', 'Better title')
        ->call('rename')
        ->assertHasNoErrors();
    expect($this->thread->fresh()->title)->toBe('Better title')
        ->and(ModerationLog::count())->toBe(0);

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $this->thread])
        ->set('renameTitle', 'Hijacked')
        ->call('rename')
        ->assertForbidden();
});

it('validates rename titles', function () {
    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(ThreadView::class, ['thread' => $this->thread])
        ->set('renameTitle', 'x')
        ->call('rename')
        ->assertHasErrors('renameTitle');
});

it('lets moderators delete a thread and logs it', function () {
    Livewire::actingAs(userWithRole(Role::MODERATOR, $this->section))
        ->test(ThreadView::class, ['thread' => $this->thread])
        ->call('deleteThread')
        ->assertRedirect(route('sections.show', $this->section));

    expect(Thread::find($this->thread->id))->toBeNull()
        ->and(ModerationLog::where('action', 'thread.delete')->exists())->toBeTrue();
});

it('marks the visible page as read', function () {
    $user = userWithRole(Role::MEMBER);

    Livewire::actingAs($user)->test(ThreadView::class, ['thread' => $this->thread]);

    expect((int) DB::table('thread_reads')->where(['user_id' => $user->id, 'thread_id' => $this->thread->id])->value('last_read_post_id'))
        ->toBe($this->thread->last_post_id);
});

it('lets moderators see new hidden posts in the banner count', function () {
    $component = Livewire::actingAs(userWithRole(Role::SENIOR_MODERATOR))->test(ThreadView::class, ['thread' => $this->thread]);
    Post::factory()->create(['thread_id' => $this->thread->id, 'is_hidden' => true]);

    $component->call('checkNew')->assertSet('newCount', 1);
});

it('asks guests to log in instead of showing the reply form', function () {
    Livewire::test(ThreadView::class, ['thread' => $this->thread])
        ->assertSee('to reply')
        ->assertDontSeeHtml('id="reply-body"');
});

it('prevents duplicate open reports from the same member', function () {
    $user = userWithRole(Role::MEMBER);
    $post = $this->thread->posts()->first();
    $report = fn () => Livewire::actingAs($user)->test(ThreadView::class, ['thread' => $this->thread])
        ->call('startReport', $post->id)->set('reportReason', 'Spam spam')->call('submitReport');

    $report();
    $report();

    expect($post->reports()->count())->toBe(1);
});

it('requires a report reason', function () {
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $this->thread])
        ->call('startReport', $this->thread->posts()->first()->id)
        ->call('submitReport')
        ->assertHasErrors('reportReason');
});

it('unhides a post without a modal', function () {
    $post = $this->thread->posts()->first();
    $post->update(['is_hidden' => true]);

    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(ThreadView::class, ['thread' => $this->thread])
        ->call('toggleHidden', $post->id)
        ->assertNotDispatched('open-modal');

    expect($post->fresh()->is_hidden)->toBeFalse();
});

it('keeps thread counters in sync when hiding posts', function () {
    Post::factory()->create(['thread_id' => $this->thread->id]);
    $this->thread->refreshStats();
    $last = $this->thread->posts()->latest('id')->first();

    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(ThreadView::class, ['thread' => $this->thread])
        ->call('toggleHidden', $last->id)
        ->set('reason', 'Spam')
        ->call('confirmReason');

    expect($this->thread->fresh()->posts_count)->toBe(1)
        ->and($this->thread->fresh()->last_post_id)->not->toBe($last->id);
});

it('highlights the post a visitor came for', function () {
    $post = $this->thread->posts()->first();

    $this->get(route('threads.show', [$this->thread->id, $this->thread->slug, 'page' => 1, 'p' => $post->id]))
        ->assertOk()->assertSeeHtml('targeted-post')->assertSee('You came here for this post');
    $this->get(route('threads.show', [$this->thread->id, $this->thread->slug, 'page' => 1]))->assertDontSeeHtml('targeted-post');
});

it('marks bookmarked posts', function () {
    $user = userWithRole(Role::MEMBER);
    $post = $this->thread->posts()->first();
    $user->bookmarks()->attach($post->id, ['created_at' => now()]);

    Livewire::actingAs($user)->test(ThreadView::class, ['thread' => $this->thread])->assertSeeHtml('bookmarked-post');
});
