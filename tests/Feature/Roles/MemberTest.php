<?php

use App\Livewire\NewThread;
use App\Livewire\ThreadView;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\Role;
use App\Models\Section;
use App\Models\Thread;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->member = userWithRole(Role::MEMBER);
});

it('can create a thread', function () {
    Livewire::actingAs($this->member)
        ->test(NewThread::class, ['section' => $this->section])
        ->set('title', 'My first thread')
        ->set('body', 'Hello **world**')
        ->call('save')
        ->assertRedirect();

    $thread = Thread::where('title', 'My first thread')->firstOrFail();
    expect($thread->posts_count)->toBe(1)
        ->and($thread->posts()->first()->body_html)->toContain('<strong>world</strong>');
});

it('can reply to a thread', function () {
    $thread = threadIn($this->section);

    Livewire::actingAs($this->member)
        ->test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', 'A reply')
        ->call('reply')
        ->assertHasNoErrors()
        ->assertSee('A reply');

    expect($thread->fresh()->posts_count)->toBe(2);
});

it('cannot reply to a locked thread', function () {
    $thread = threadIn($this->section);
    $thread->update(['is_locked' => true]);

    Livewire::actingAs($this->member)
        ->test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', 'A reply')
        ->call('reply')
        ->assertForbidden();
});

it('can toggle a reaction', function () {
    $thread = threadIn($this->section);
    $post = $thread->posts()->first();
    $component = Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread]);

    $component->call('react', $post->id, 'like');
    expect(Reaction::where('post_id', $post->id)->count())->toBe(1);

    $component->call('react', $post->id, 'like');
    expect(Reaction::where('post_id', $post->id)->count())->toBe(0);
});

it('can report a post of someone else but not its own', function () {
    $thread = threadIn($this->section);
    $post = $thread->posts()->first();

    Livewire::actingAs($this->member)
        ->test(ThreadView::class, ['thread' => $thread])
        ->call('startReport', $post->id)
        ->set('reportReason', 'This is spam')
        ->call('submitReport')
        ->assertHasNoErrors();

    expect(Report::where('post_id', $post->id)->where('status', 'open')->exists())->toBeTrue();

    $own = threadIn($this->section, $this->member);
    Livewire::actingAs($this->member)
        ->test(ThreadView::class, ['thread' => $own])
        ->call('startReport', $own->posts()->first()->id)
        ->assertForbidden();
});

it('can edit its own post but not others', function () {
    $own = threadIn($this->section, $this->member);
    $ownPost = $own->posts()->first();

    Livewire::actingAs($this->member)
        ->test(ThreadView::class, ['thread' => $own])
        ->call('startEdit', $ownPost->id)
        ->set('editBody', 'Edited text')
        ->call('saveEdit');
    expect($ownPost->fresh()->body)->toBe('Edited text')->and($ownPost->fresh()->edited_at)->not->toBeNull();

    $other = threadIn($this->section);
    Livewire::actingAs($this->member)
        ->test(ThreadView::class, ['thread' => $other])
        ->call('startEdit', $other->posts()->first()->id)
        ->assertForbidden();
});

it('cannot moderate', function () {
    $thread = threadIn($this->section);
    $post = $thread->posts()->first();

    $test = fn () => Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread]);
    $test()->call('toggleLock')->assertForbidden();
    $test()->call('togglePin')->assertForbidden();
    $test()->call('toggleHidden', $post->id)->assertForbidden();
    expect($thread->fresh()->is_locked)->toBeFalse()->and($post->fresh()->is_hidden)->toBeFalse();

    $this->actingAs($this->member)->get(route('moderation'))->assertForbidden();
    $this->actingAs($this->member)->get(route('admin.users'))->assertForbidden();
});

it('cannot see private sections', function () {
    $private = Section::factory()->private()->create();

    $this->actingAs($this->member)->get(route('sections.show', $private))->assertForbidden();
});

it('is blocked when banned', function () {
    sanction($this->member);
    $thread = threadIn($this->section);

    Livewire::actingAs($this->member)
        ->test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', 'hello')
        ->call('reply')
        ->assertForbidden();
});

it('is rate limited when posting too fast', function () {
    $thread = threadIn($this->section);
    $component = Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread]);

    foreach (range(1, 5) as $i) {
        $component->set('replyBody', "reply $i")->call('reply')->assertHasNoErrors();
    }
    $component->set('replyBody', 'one too many')->call('reply')->assertHasErrors('replyBody');
    expect(Post::where('thread_id', $thread->id)->count())->toBe(6);
});

it('gets sanitized html output', function () {
    $thread = threadIn($this->section);

    Livewire::actingAs($this->member)
        ->test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', "Hi <script>alert(1)</script> [x](javascript:alert(1)) <img src=x onerror=alert(1)> <b onclick=x>b</b>")
        ->call('reply');

    $html = Post::latest('id')->first()->body_html;
    expect($html)->not->toContain('<script')->not->toContain('href="javascript')->not->toContain('<img')->not->toContain('<b onclick');
});
