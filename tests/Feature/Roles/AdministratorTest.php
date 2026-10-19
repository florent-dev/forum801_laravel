<?php

use App\Livewire\Admin\Sections;
use App\Livewire\Admin\Users;
use App\Livewire\SanctionModal;
use App\Livewire\ThreadView;
use App\Models\ModerationLog;
use App\Models\Role;
use App\Models\Section;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(Role::ADMINISTRATOR);
});

it('can open every admin page', function () {
    foreach (['admin.users', 'admin.sections', 'admin.roles', 'moderation'] as $route) {
        $this->actingAs($this->admin)->get(route($route))->assertOk();
    }
});

it('can suspend permanently and lift the suspension', function () {
    $target = userWithRole(Role::MEMBER);

    Livewire::actingAs($this->admin)->test(SanctionModal::class)
        ->call('open', $target->id)
        ->set('type', 'suspension')
        ->set('days', 'forever')
        ->set('reason', 'Spamming the forum')
        ->call('apply')
        ->assertHasNoErrors();

    $sanction = $target->sanctions()->first();
    expect($target->fresh()->isBanned())->toBeTrue()
        ->and($sanction->expires_at)->toBeNull()
        ->and($target->fresh()->status)->toBe('suspended')
        ->and(ModerationLog::where('action', 'user.suspension')->exists())->toBeTrue();

    Livewire::actingAs($this->admin)->test(SanctionModal::class)->call('open', $target->id)->call('revoke', $sanction->id);
    expect($target->fresh()->isBanned())->toBeFalse()->and($target->fresh()->status)->toBe('active');
});

it('requires a reason to sanction', function () {
    $target = userWithRole(Role::MEMBER);

    Livewire::actingAs($this->admin)->test(SanctionModal::class)
        ->call('open', $target->id)
        ->set('type', 'suspension')
        ->call('apply')
        ->assertHasErrors('reason');
    expect($target->fresh()->isBanned())->toBeFalse();
});

it('lets suspensions expire', function () {
    $target = sanction(userWithRole(Role::MEMBER), 'suspension', 'old', now()->subMinute());

    expect($target->fresh()->isBanned())->toBeFalse();
});

it('can assign scoped roles', function () {
    $target = userWithRole(Role::MEMBER);
    $section = Section::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('select', $target->id)
        ->set('newRole', Role::MODERATOR)
        ->set('newSectionId', $section->id)
        ->call('addRole')
        ->assertHasNoErrors();

    expect($target->fresh()->hasRole(Role::MODERATOR, $section))->toBeTrue();
});

it('requires a section for section scoped roles', function () {
    $target = userWithRole(Role::MEMBER);

    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('select', $target->id)
        ->set('newRole', Role::SECTION_OWNER)
        ->call('addRole')
        ->assertHasErrors('newSectionId');
});

it('can create sections', function () {
    Livewire::actingAs($this->admin)
        ->test(Sections::class)
        ->set('form.name', 'Brand New')
        ->set('form.visibility', 'private')
        ->call('save')
        ->assertHasNoErrors();

    expect(Section::where('slug', 'brand-new')->where('visibility', 'private')->exists())->toBeTrue();
});

it('can moderate private sections too', function () {
    $thread = threadIn(Section::factory()->private()->create());

    Livewire::actingAs($this->admin)
        ->test(ThreadView::class, ['thread' => $thread])
        ->call('toggleLock')
        ->assertHasNoErrors();

    expect($thread->fresh()->is_locked)->toBeTrue();
});

it('blocks admin components for non admins', function () {
    Livewire::actingAs(userWithRole(Role::SENIOR_MODERATOR))->test(Users::class)->assertForbidden();
});
