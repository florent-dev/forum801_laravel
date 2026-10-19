<?php

use App\Livewire\NewThread;
use App\Livewire\SanctionModal;
use App\Livewire\ThreadView;
use App\Models\Role;
use App\Models\Sanction;
use App\Models\Section;
use App\Services\SanctionService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->member = userWithRole(Role::MEMBER);
});

it('lets section moderators warn, mute and suspend members', function () {
    $mod = userWithRole(Role::MODERATOR, $this->section);

    foreach (['warning', 'mute', 'suspension'] as $type) {
        Livewire::actingAs($mod)->test(SanctionModal::class)
            ->call('open', $this->member->id)
            ->set('type', $type)->set('days', '3')->set('reason', 'Rule '.$type)
            ->call('apply')->assertHasNoErrors();
    }

    expect($this->member->sanctions()->count())->toBe(3)
        ->and($this->member->fresh()->isMuted())->toBeTrue()
        ->and($this->member->fresh()->isBanned())->toBeTrue()
        ->and($this->member->notifications()->count())->toBe(3)
        ->and($this->member->sanctions()->where('type', 'warning')->first()->expires_at)->toBeNull();
});

it('caps sanction length by rank', function () {
    $mod = userWithRole(Role::MODERATOR, $this->section);
    $senior = userWithRole(Role::SENIOR_MODERATOR);
    $service = app(SanctionService::class);

    expect(fn () => $service->apply($mod, $this->member, 'mute', 30, 'too long'))->toThrow(ValidationException::class)
        ->and(fn () => $service->apply($senior, $this->member, 'mute', null, 'forever'))->toThrow(ValidationException::class);
    expect($service->apply($senior, $this->member, 'mute', 30, 'ok')->expires_at->isFuture())->toBeTrue();
});

it('only offers allowed durations in the dialog', function () {
    Livewire::actingAs(userWithRole(Role::MODERATOR, $this->section))->test(SanctionModal::class)
        ->call('open', $this->member->id)
        ->assertViewHas('durations', fn ($d) => ! array_key_exists('forever', $d) && ! array_key_exists(30, $d));

    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(SanctionModal::class)
        ->call('open', $this->member->id)
        ->assertViewHas('durations', fn ($d) => array_key_exists('forever', $d));
});

it('forbids sanctioning peers, superiors and oneself', function () {
    $mod = userWithRole(Role::MODERATOR, $this->section);
    $senior = userWithRole(Role::SENIOR_MODERATOR);
    $admin = userWithRole(Role::ADMINISTRATOR);

    Livewire::actingAs($mod)->test(SanctionModal::class)->call('open', userWithRole(Role::MODERATOR, $this->section)->id)->assertForbidden();
    Livewire::actingAs($mod)->test(SanctionModal::class)->call('open', $senior->id)->assertForbidden();
    Livewire::actingAs($senior)->test(SanctionModal::class)->call('open', $admin->id)->assertForbidden();
    Livewire::actingAs($mod)->test(SanctionModal::class)->call('open', $mod->id)->assertForbidden();
    Livewire::actingAs($this->member)->test(SanctionModal::class)->call('open', userWithRole(Role::MEMBER)->id)->assertForbidden();
});

it('stops muted members from posting but lets them read and report', function () {
    $thread = threadIn($this->section);
    sanction($this->member, 'mute');

    Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread])
        ->assertOk()
        ->set('replyBody', 'hello')->call('reply')->assertForbidden();
    Livewire::actingAs($this->member)->test(NewThread::class, ['section' => $this->section])->assertForbidden();
    Livewire::actingAs($this->member)->test(ThreadView::class, ['thread' => $thread])
        ->call('startReport', $thread->posts()->first()->id)->assertOk();
});

it('lifts expired mutes automatically', function () {
    sanction($this->member, 'mute', 'old', now()->subSecond());

    expect($this->member->fresh()->isMuted())->toBeFalse()->and($this->member->fresh()->canParticipate())->toBeTrue();
});

it('shows the sanction to the member in a banner', function () {
    sanction($this->member, 'mute', 'Calm down');

    $this->actingAs($this->member)->get('/')->assertSee('You are muted until')->assertSee('Calm down');
});

it('only lets the dialog revoke sanctions of the opened player', function () {
    $other = sanction(userWithRole(Role::MEMBER));
    $sanctionId = $other->sanctions()->first()->id;

    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(SanctionModal::class)
        ->call('open', $this->member->id)
        ->call('revoke', $sanctionId);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('offers the sanction entry in the post author menu to moderators only', function () {
    $thread = threadIn($this->section, $this->member);

    Livewire::actingAs(userWithRole(Role::MODERATOR, $this->section))->test(ThreadView::class, ['thread' => $thread])->assertSee('Sanction…');
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $thread])->assertDontSee('Sanction…');
});
