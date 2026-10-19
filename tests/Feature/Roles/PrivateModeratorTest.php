<?php

use App\Livewire\ThreadView;
use App\Models\Role;
use App\Models\Section;
use Livewire\Livewire;

beforeEach(function () {
    $this->private = Section::factory()->private()->create();
    $this->public = Section::factory()->create();
    $this->pmod = userWithRole(Role::PRIVATE_MODERATOR);
});

it('can see and moderate private sections', function () {
    $thread = threadIn($this->private);

    $this->actingAs($this->pmod)->get($thread->url())->assertOk();
    Livewire::actingAs($this->pmod)
        ->test(ThreadView::class, ['thread' => $thread])
        ->call('toggleLock')
        ->assertHasNoErrors();

    expect($thread->fresh()->is_locked)->toBeTrue();
});

it('cannot moderate public sections', function () {
    $thread = threadIn($this->public);

    Livewire::actingAs($this->pmod)
        ->test(ThreadView::class, ['thread' => $thread])
        ->call('toggleLock')
        ->assertForbidden();
});

it('can be scoped to a single private section', function () {
    $other = Section::factory()->private()->create();
    $scoped = userWithRole(Role::PRIVATE_MODERATOR, $this->private);

    $this->actingAs($scoped)->get(threadIn($this->private)->url())->assertOk();
    $this->actingAs($scoped)->get(threadIn($other)->url())->assertForbidden();
});
