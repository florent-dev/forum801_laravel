<?php

use App\Livewire\Admin\Sections;
use App\Livewire\NewThread;
use App\Livewire\ThreadView;
use App\Models\Role;
use App\Models\Section;
use App\Services\ForumService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(Role::ADMINISTRATOR);
    $this->section = Section::factory()->create(['name' => 'Staff room']);
    $this->member = userWithRole(Role::MEMBER);
    $this->moderator = userWithRole(Role::MODERATOR, $this->section);
});

it('saves every section setting from the admin form', function () {
    Livewire::actingAs($this->admin)->test(Sections::class)
        ->call('edit', $this->section->id)
        ->assertSet('form.view_roles', fn ($roles) => in_array(Section::GUEST, $roles, true))
        ->set('form.view_roles', [Role::MODERATOR, Role::SENIOR_MODERATOR])
        ->set('form.is_archived', true)
        ->set('form.polls_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    $section = $this->section->fresh();
    expect($section->denied_view_roles)->toContain(Section::GUEST, Role::MEMBER)->not->toContain(Role::MODERATOR)
        ->and($section->is_archived)->toBeTrue()
        ->and($section->polls_enabled)->toBeFalse();
});

it('hides a section from denied roles, in the policy and in every list alike', function () {
    $this->section->update(['denied_view_roles' => [Section::GUEST, Role::MEMBER]]);
    $other = Section::factory()->create();
    $thread = threadIn($this->section);
    $elsewhere = userWithRole(Role::MODERATOR, $other);

    foreach ([[null, false], [$this->member, false], [$elsewhere, false], [$this->moderator, true], [$this->admin, true]] as [$user, $allowed]) {
        $listed = Section::visibleTo($user)->whereKey($this->section->id)->exists();
        expect(Gate::forUser($user)->allows('view', $this->section))->toBe($allowed)
            ->and($listed)->toBe($allowed)
            ->and(Section::visibleTo($user)->whereKey($other->id)->exists())->toBeTrue();
    }

    $this->actingAs($this->member)->get(route('threads.show', $thread))->assertForbidden();
    auth()->logout();
    $this->get(route('feeds.section', $this->section))->assertNotFound();
    $this->get(route('feeds.latest'))->assertOk()->assertDontSee($thread->title);
});

it('keeps an archived section readable but closed to non-moderators', function () {
    $thread = threadIn($this->section, $this->member);
    $post = $thread->posts()->first();
    $this->section->update(['is_archived' => true]);
    $thread = $thread->fresh();

    expect($this->member->can('view', $thread))->toBeTrue()
        ->and($this->member->can('createThread', $this->section))->toBeFalse()
        ->and($this->member->can('reply', $thread))->toBeFalse()
        ->and($this->member->can('update', $post->fresh()))->toBeFalse()
        ->and($this->member->can('react', $post->fresh()))->toBeFalse()
        ->and($this->moderator->can('reply', $thread))->toBeTrue()
        ->and($this->moderator->can('createThread', $this->section))->toBeTrue();

    Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread])
        ->assertSee(__('This section is archived: it is read-only.'));
});

it('turns polls off in a section', function () {
    $this->section->update(['polls_enabled' => false]);
    $poll = ['question' => 'Best cheese?', 'options' => ['Brie', 'Comté']];

    expect(fn () => app(ForumService::class)->createThread($this->member, $this->section, 'Vote', 'Hi', null, $poll))
        ->toThrow(ValidationException::class);
    $thread = app(ForumService::class)->createThread($this->member, $this->section, 'No vote', 'Hi');
    expect(fn () => app(ForumService::class)->reply($this->member, $thread, 'Reply', $poll))->toThrow(ValidationException::class);

    Livewire::actingAs($this->member)->test(NewThread::class, ['section' => $this->section])->assertDontSee(__('Attach a poll'));
    Livewire::actingAs($this->member)->test(NewThread::class, ['section' => Section::factory()->create()])->assertSee(__('Attach a poll'));
});
