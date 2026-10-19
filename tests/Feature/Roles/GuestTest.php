<?php

use App\Livewire\ThreadView;
use App\Models\Section;
use Livewire\Livewire;

it('lets guests browse public sections and threads', function () {
    $section = Section::factory()->create();
    $thread = threadIn($section);

    $this->get('/')->assertOk()->assertSee($section->name);
    $this->get(route('sections.show', $section))->assertOk();
    $this->get($thread->url())->assertOk()->assertSee($thread->title);
});

it('hides private sections from guests', function () {
    $private = Section::factory()->private()->create();
    $thread = threadIn($private);

    $this->get('/')->assertDontSee($private->name);
    $this->get(route('sections.show', $private))->assertForbidden();
    $this->get($thread->url())->assertForbidden();
});

it('prevents guests from replying', function () {
    $thread = threadIn(Section::factory()->create());

    Livewire::test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', 'hello there')
        ->call('reply')
        ->assertForbidden();
});

it('redirects guests away from moderation and admin pages', function () {
    $this->get(route('moderation'))->assertRedirect('/login');
    $this->get(route('admin.users'))->assertRedirect('/login');
});
