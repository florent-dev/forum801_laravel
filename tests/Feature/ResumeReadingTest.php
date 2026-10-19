<?php

use App\Livewire\ThreadView;
use App\Models\Post;
use App\Models\Role;
use App\Models\Section;
use App\Services\ForumService;
use Livewire\Livewire;

beforeEach(function () {
    $this->thread = threadIn(Section::factory()->create());
    Post::factory()->count(Post::PER_PAGE * 2)->create(['thread_id' => $this->thread->id]);
    $this->thread->refreshStats();
    $this->user = userWithRole(Role::MEMBER);
    $this->posts = Post::where('thread_id', $this->thread->id)->orderBy('id')->get();
});

it('opens the page of the first unread post', function () {
    app(ForumService::class)->markRead($this->user, $this->thread, $this->posts[Post::PER_PAGE + 2]->id);

    $this->actingAs($this->user)->get($this->thread->url())
        ->assertRedirect(route('threads.show', ['thread' => $this->thread->id, 'slug' => $this->thread->slug, 'page' => 2]));
});

it('opens the last read page when everything is read', function () {
    app(ForumService::class)->markRead($this->user, $this->thread, $this->posts->last()->id);

    $this->actingAs($this->user)->get($this->thread->url())
        ->assertRedirect(route('threads.show', ['thread' => $this->thread->id, 'slug' => $this->thread->slug, 'page' => 3]));
});

it('starts on page 1 for unread threads, guests and explicit pages', function () {
    $this->actingAs($this->user)->get($this->thread->url())->assertOk();
    auth()->logout();
    $this->get($this->thread->url())->assertOk();
    app(ForumService::class)->markRead($this->user, $this->thread, $this->posts->last()->id);
    $this->actingAs($this->user)->get($this->thread->url(1))->assertOk();
});

it('keeps the left list state when resuming', function () {
    app(ForumService::class)->markRead($this->user, $this->thread, $this->posts->last()->id);

    $this->actingAs($this->user)->get($this->thread->url().'?tp=2&sort=views')
        ->assertRedirectContains('tp=2')->assertRedirectContains('sort=views');
});

it('shows a divider before the first new message', function () {
    app(ForumService::class)->markRead($this->user, $this->thread, $this->posts[3]->id);

    Livewire::actingAs($this->user)->test(ThreadView::class, ['thread' => $this->thread])
        ->assertSet('readMarkId', $this->posts[3]->id)
        ->assertSeeHtmlInOrder(['id="post-'.$this->posts[3]->id.'"', 'unread-divider', 'id="post-'.$this->posts[4]->id.'"']);
});

it('shows no divider on a first visit', function () {
    Livewire::actingAs($this->user)->test(ThreadView::class, ['thread' => $this->thread])->assertDontSeeHtml('unread-divider');
});

it('flags fully read threads so the page opens at the bottom', function () {
    app(ForumService::class)->markRead($this->user, $this->thread, $this->posts->last()->id);

    Livewire::actingAs($this->user)->test(ThreadView::class, ['thread' => $this->thread])->assertSeeHtml('data-all-read');
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $this->thread])->assertDontSeeHtml('data-all-read');
});

it('drops the new messages divider once you reply', function () {
    app(ForumService::class)->markRead($this->user, $this->thread, $this->posts[3]->id);

    Livewire::actingAs($this->user)->test(ThreadView::class, ['thread' => $this->thread])
        ->assertSeeHtml('unread-divider')
        ->set('replyBody', 'My answer')
        ->call('reply')
        ->assertDontSeeHtml('unread-divider');
});
