<?php

use App\Livewire\ThreadView;
use App\Models\Post;
use App\Models\Role;
use App\Models\Section;
use Livewire\Livewire;

it('shows a banner instead of inserting new posts', function () {
    $thread = threadIn(Section::factory()->create());
    $reader = userWithRole(Role::MEMBER);
    $component = Livewire::actingAs($reader)->test(ThreadView::class, ['thread' => $thread]);

    $late = Post::factory()->create(['thread_id' => $thread->id, 'body_html' => '<p>late arrival</p>']);

    $component->call('checkNew')
        ->assertSet('newCount', 1)
        ->assertSee('1 new post')
        ->assertDontSee('late arrival')
        ->call('showNew')
        ->assertSet('newCount', 0)
        ->assertSee('late arrival');
});

it('does not count hidden posts as new for members', function () {
    $thread = threadIn(Section::factory()->create());
    $component = Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $thread]);

    Post::factory()->create(['thread_id' => $thread->id, 'is_hidden' => true]);

    $component->call('checkNew')->assertSet('newCount', 0);
});

it('protects the ceiling from client tampering', function () {
    $thread = threadIn(Section::factory()->create());

    Livewire::test(ThreadView::class, ['thread' => $thread])
        ->set('ceilingId', 999999);
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
