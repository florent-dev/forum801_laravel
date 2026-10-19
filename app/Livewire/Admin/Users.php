<?php

namespace App\Livewire\Admin;

use App\Models\ModerationLog;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Users extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $selectedId = null;

    public string $newRole = 'moderator';

    public ?int $newSectionId = null;

    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'user-roles');
    }

    public function addRole(): void
    {
        $this->authorize('admin');
        $user = User::findOrFail((int) $this->selectedId);
        $this->validate([
            'newRole' => ['required', 'exists:roles,name'],
            'newSectionId' => [in_array($this->newRole, Role::SECTION_SCOPED, true) ? 'required' : 'nullable', 'nullable', 'exists:sections,id'],
        ]);
        $section = $this->newSectionId ? Section::find($this->newSectionId) : null;
        if (! $user->hasRole($this->newRole, $section) || ($section && $user->hasGlobalRole($this->newRole))) {
            $user->assignRole($this->newRole, $section);
            ModerationLog::record(auth()->user(), 'role.assign', $user, $section?->id, ['role' => $this->newRole]);
        }
        $this->reset('newSectionId');
        $this->dispatch('toast', message: __('Role added.'));
    }

    public function removeRole(int $pivotId): void
    {
        $this->authorize('admin');
        $user = User::findOrFail((int) $this->selectedId);
        $row = $user->roles()->wherePivot('id', $pivotId)->firstOrFail();
        abort_if($user->id === auth()->id() && $row->name === Role::ADMINISTRATOR, 422, 'You cannot remove your own administrator role.');
        $user->roles()->newPivotStatement()->where('id', $pivotId)->delete();
        ModerationLog::record(auth()->user(), 'role.remove', $user, $row->pivot->scope_id, ['role' => $row->name]);
    }

    public function render()
    {
        $users = User::with('roles')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.$this->search.'%')->orWhere('email', 'ilike', '%'.$this->search.'%')))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.admin.users', [
            'users' => $users,
            'selected' => $this->selectedId ? User::with('roles')->find($this->selectedId) : null,
            'roles' => Role::orderBy('rank')->get(),
            'sections' => Section::orderBy('position')->get(),
        ]);
    }

    public function paginationView(): string
    {
        return 'partials.pager';
    }
}
