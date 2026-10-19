<?php

use App\Livewire\SectionSettings;
use App\Livewire\ThreadView;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->owner = userWithRole(Role::SECTION_OWNER, $this->section);
});

it('can update its section settings', function () {
    $this->actingAs($this->owner)->get(route('sections.settings', $this->section))->assertOk();

    Livewire::actingAs($this->owner)
        ->test(SectionSettings::class, ['section' => $this->section])
        ->set('description', 'New description')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->section->fresh()->description)->toBe('New description');
});

it('can add and remove moderators of its section', function () {
    $candidate = userWithRole(Role::MEMBER);

    Livewire::actingAs($this->owner)
        ->test(SectionSettings::class, ['section' => $this->section])
        ->set('moderatorName', $candidate->name)
        ->call('addModerator')
        ->assertHasNoErrors();

    expect($candidate->fresh()->hasRole(Role::MODERATOR, $this->section))->toBeTrue();

    $pivotId = $candidate->fresh()->roles()->where('name', Role::MODERATOR)->first()->pivot->id;
    Livewire::actingAs($this->owner)
        ->test(SectionSettings::class, ['section' => $this->section])
        ->call('removeModerator', $pivotId);

    expect(User::find($candidate->id)->hasRole(Role::MODERATOR, $this->section))->toBeFalse();
});

it('moderates its own section', function () {
    $thread = threadIn($this->section);

    Livewire::actingAs($this->owner)
        ->test(ThreadView::class, ['thread' => $thread])
        ->call('togglePin')
        ->assertHasNoErrors();

    expect($thread->fresh()->is_pinned)->toBeTrue();
});

it('cannot manage other sections', function () {
    $other = Section::factory()->create();

    $this->actingAs($this->owner)->get(route('sections.settings', $other))->assertForbidden();
    Livewire::actingAs($this->owner)
        ->test(ThreadView::class, ['thread' => threadIn($other)])
        ->call('toggleLock')
        ->assertForbidden();
});
