<?php

use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Sections;
use App\Livewire\Admin\Users;
use App\Models\ModerationLog;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->admin = userWithRole(Role::ADMINISTRATOR));

it('edits a section and logs it', function () {
    $section = Section::factory()->create(['name' => 'Old name']);

    Livewire::actingAs($this->admin)->test(Sections::class)
        ->call('edit', $section->id)
        ->set('form.name', 'New name')
        ->set('form.community', 'fr')
        ->call('save')
        ->assertHasNoErrors();

    expect($section->fresh()->name)->toBe('New name')
        ->and($section->fresh()->community)->toBe('fr')
        ->and(ModerationLog::where('action', 'section.update')->exists())->toBeTrue();
});

it('validates section fields', function () {
    Livewire::actingAs($this->admin)->test(Sections::class)
        ->set('form.name', 'Bad')
        ->set('form.community', 'xx')
        ->set('form.icon', 'javascript:alert(1)')
        ->set('form.visibility', 'secret')
        ->call('save')
        ->assertHasErrors(['form.community', 'form.icon', 'form.visibility']);
});

it('deletes a section and logs it', function () {
    $section = Section::factory()->create();

    Livewire::actingAs($this->admin)->test(Sections::class)->call('delete', $section->id);

    expect(Section::find($section->id))->toBeNull()
        ->and(ModerationLog::where('action', 'section.delete')->exists())->toBeTrue();
});

it('updates role labels and colors', function () {
    $role = Role::where('name', Role::MODERATOR)->first();

    Livewire::actingAs($this->admin)->test(Roles::class)
        ->set("colors.{$role->id}", '#123456')
        ->set("labels.{$role->id}", 'Guardian')
        ->call('save', $role->id)
        ->assertHasNoErrors();

    expect($role->fresh()->color)->toBe('#123456')->and($role->fresh()->label)->toBe('Guardian');
});

it('rejects invalid role colors', function () {
    $role = Role::first();

    Livewire::actingAs($this->admin)->test(Roles::class)
        ->set("colors.{$role->id}", 'red;}')
        ->call('save', $role->id)
        ->assertHasErrors("colors.{$role->id}");
});

it('removes a role from a user', function () {
    $target = userWithRole(Role::SENIOR_MODERATOR);
    $pivotId = $target->roles()->where('name', Role::SENIOR_MODERATOR)->first()->pivot->id;

    Livewire::actingAs($this->admin)->test(Users::class)->call('select', $target->id)->call('removeRole', $pivotId);

    expect($target->fresh()->hasRole(Role::SENIOR_MODERATOR))->toBeFalse();
});

it('cannot remove its own administrator role', function () {
    $pivotId = $this->admin->roles()->where('name', Role::ADMINISTRATOR)->first()->pivot->id;

    Livewire::actingAs($this->admin)->test(Users::class)->call('select', $this->admin->id)->call('removeRole', $pivotId)->assertStatus(422);

    expect($this->admin->fresh()->isAdmin())->toBeTrue();
});

it('cannot sanction itself', function () {
    Livewire::actingAs($this->admin)->test(\App\Livewire\SanctionModal::class)->call('open', $this->admin->id)->assertForbidden();
});

it('searches users by name or email', function () {
    User::factory()->create(['name' => 'findme', 'email' => 'x@find.test']);
    User::factory()->create(['name' => 'other', 'email' => 'y@other.test']);

    Livewire::actingAs($this->admin)->test(Users::class)->set('search', 'find')->assertSee('findme')->assertDontSee('y@other.test');
});
