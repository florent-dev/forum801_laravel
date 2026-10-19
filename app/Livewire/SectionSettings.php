<?php

namespace App\Livewire;

use App\Models\ModerationLog;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SectionSettings extends Component
{
    public Section $section;

    public string $description = '';

    public ?string $icon = null;


    public string $moderatorName = '';

    public function mount(Section $section): void
    {
        $this->authorize('update', $section);
        $this->description = (string) $section->description;
        $this->icon = $section->icon;
    }

    public function save(): void
    {
        $this->authorize('update', $this->section);
        $data = $this->validate([
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:255', 'regex:#^img/sections/[A-Za-z0-9_\-]+\.png$#'],
        ]);
        $this->section->update($data);
        ModerationLog::record(auth()->user(), 'section.update', $this->section, $this->section->id, ['by' => 'owner']);
        $this->dispatch('toast', message: __('Settings saved.'));
    }

    public function addModerator(): void
    {
        $this->authorize('manageModerators', $this->section);
        $this->validate(['moderatorName' => ['required', 'exists:users,name']]);
        $user = User::where('name', $this->moderatorName)->firstOrFail();
        $role = $this->section->isPrivate() ? Role::PRIVATE_MODERATOR : Role::MODERATOR;
        if (! $user->hasRole($role, $this->section) || $user->hasGlobalRole($role)) {
            $user->assignRole($role, $this->section);
        }
        ModerationLog::record(auth()->user(), 'section.moderator_add', $user, $this->section->id, ['role' => $role]);
        $this->reset('moderatorName');
        $this->dispatch('toast', message: __(':name now moderates :section.', ['name' => $user->name, 'section' => $this->section->name]));
    }

    public function removeModerator(int $pivotId): void
    {
        $this->authorize('manageModerators', $this->section);
        $row = DB::table('role_user')->where('id', $pivotId)
            ->where('scope_type', 'section')->where('scope_id', $this->section->id)
            ->whereIn('role_id', Role::whereIn('name', [Role::MODERATOR, Role::PRIVATE_MODERATOR])->pluck('id'))
            ->first();
        abort_unless($row, 404);
        DB::table('role_user')->where('id', $pivotId)->delete();
        ModerationLog::record(auth()->user(), 'section.moderator_remove', User::findOrFail($row->user_id), $this->section->id);
    }

    public function render()
    {
        $staff = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('users', 'users.id', '=', 'role_user.user_id')
            ->where('scope_type', 'section')->where('scope_id', $this->section->id)
            ->select('role_user.id', 'users.name', 'roles.name as role', 'roles.label', 'roles.color')
            ->orderBy('users.name')->get();

        return view('livewire.section-settings', [
            'staff' => $staff,
            'icons' => collect(glob(public_path('img/sections/*.png')))->map(fn ($p) => 'img/sections/'.basename($p)),
        ]);
    }
}
