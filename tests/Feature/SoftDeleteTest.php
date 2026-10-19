<?php

use App\Livewire\ModerationQueue;
use App\Livewire\ThreadView;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Role;
use App\Models\Section;
use App\Models\Thread;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->thread = threadIn($this->section);
    Post::factory()->count(3)->create(['thread_id' => $this->thread->id]);
    $this->thread->refreshStats();
    $this->mod = userWithRole(Role::MODERATOR, $this->section);
});

it('soft deletes a thread and keeps it in the database', function () {
    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread])->call('deleteThread');

    $trashed = Thread::withTrashed()->find($this->thread->id);
    expect($trashed)->not->toBeNull()
        ->and($trashed->trashed())->toBeTrue()
        ->and($trashed->deleted_by)->toBe($this->mod->id)
        ->and($trashed->posts()->count())->toBe(4);
});

it('restores a deleted thread from the thread page', function () {
    $this->thread->delete();

    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => Thread::withTrashed()->find($this->thread->id)])
        ->assertSee('This thread was deleted')
        ->call('restoreThread');

    expect($this->thread->fresh()->trashed())->toBeFalse()
        ->and(ModerationLog::where('action', 'thread.restore')->exists())->toBeTrue();
});

it('lists deleted threads in the moderation queue and restores them in bulk', function () {
    $other = threadIn($this->section);
    $this->thread->delete();
    $other->delete();
    $foreign = threadIn(Section::factory()->create(['name' => 'Elsewhere']));
    $foreign->update(['title' => 'Foreign deleted']);
    $foreign->delete();

    Livewire::actingAs($this->mod)->test(ModerationQueue::class)
        ->call('setTab', 'deleted')
        ->assertSee($this->thread->title)
        ->assertDontSee('Foreign deleted')
        ->call('restoreThreads', [$this->thread->id, $other->id]);

    expect(Thread::count())->toBe(2)->and(Thread::onlyTrashed()->count())->toBe(1);
});

it('cannot restore threads outside its sections', function () {
    $foreign = threadIn(Section::factory()->create());
    $foreign->delete();

    Livewire::actingAs($this->mod)->test(ModerationQueue::class)->call('restoreThreads', [$foreign->id])->assertForbidden();
});

it('removes deleted threads from member views', function () {
    $this->thread->delete();
    $member = userWithRole(Role::MEMBER);

    $this->actingAs($member)->get(route('posts.show', $this->thread->posts()->first()))->assertForbidden();
    expect($this->get(route('search', ['q' => $this->thread->title]))->viewData('results')->total())->toBe(0)
        ->and($this->section->threads()->count())->toBe(0);
});

it('deletes and restores a single post', function () {
    $post = $this->thread->posts()->latest('id')->first();
    $component = Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread]);

    $component->call('deletePost', $post->id);
    expect($post->fresh()->trashed())->toBeTrue()->and($this->thread->fresh()->posts_count)->toBe(3);

    $component->call('restorePost', $post->id);
    expect($post->fresh()->trashed())->toBeFalse()->and($this->thread->fresh()->posts_count)->toBe(4);
});

it('bulk hides, deletes and restores posts', function () {
    $ids = $this->thread->posts()->pluck('id')->take(3)->map(fn ($id) => (string) $id)->all();
    $component = Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread])->call('toggleSelectMode');

    $component->set('selected', $ids)->call('bulk', 'hide')->assertDispatched('open-modal')
        ->set('reason', 'Flood')->call('confirmReason');
    expect(Post::where('is_hidden', true)->where('hide_reason', 'Flood')->count())->toBe(3);

    $component->set('selected', $ids)->call('bulk', 'unhide');
    expect(Post::where('is_hidden', true)->count())->toBe(0);

    $component->set('selected', $ids)->call('bulk', 'delete');
    expect(Post::onlyTrashed()->count())->toBe(3)->and($this->thread->fresh()->posts_count)->toBe(1);

    $component->call('toggleShowDeleted')->set('selected', $ids)->call('bulk', 'restore');
    expect(Post::onlyTrashed()->count())->toBe(0)->and($this->thread->fresh()->posts_count)->toBe(4);
});

it('shows deleted posts to moderators only when asked', function () {
    $post = $this->thread->posts()->latest('id')->first();
    $post->update(['body_html' => '<p>soon deleted</p>']);
    $post->delete();

    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread])
        ->assertDontSee('soon deleted')
        ->call('toggleShowDeleted')
        ->assertSee('soon deleted');

    Livewire::actingAs($post->user)->test(ThreadView::class, ['thread' => $this->thread])->assertDontSee('soon deleted');
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $this->thread])->call('toggleShowDeleted')->assertForbidden();
});

it('forbids bulk post actions to members', function () {
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $this->thread])
        ->set('selected', [(string) $this->thread->posts()->first()->id])
        ->call('bulk', 'delete')
        ->assertForbidden();
});

it('cannot reply or react in a deleted thread', function () {
    $this->thread->delete();
    $thread = Thread::withTrashed()->find($this->thread->id);

    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', 'hello')->call('reply')->assertForbidden();
});
