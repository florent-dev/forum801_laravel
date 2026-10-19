<?php

namespace App\Livewire\Admin;

use App\Models\ModerationLog;
use App\Models\Role;
use Livewire\Component;

class Roles extends Component
{
    public array $colors = [];

    public array $labels = [];

    public function mount(): void
    {
        $this->authorize('admin');
        foreach (Role::all() as $role) {
            $this->colors[$role->id] = $role->color;
            $this->labels[$role->id] = $role->label;
        }
    }

    public function save(int $id): void
    {
        $this->authorize('admin');
        $this->validate([
            "colors.$id" => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            "labels.$id" => ['required', 'string', 'max:40'],
        ]);
        $role = Role::findOrFail($id);
        $role->update(['color' => $this->colors[$id], 'label' => $this->labels[$id]]);
        ModerationLog::record(auth()->user(), 'role.update', $role, null, ['role' => $role->name]);
        $this->dispatch('toast', message: __(':name saved.', ['name' => $role->label]));
    }

    public function render()
    {
        return view('livewire.admin.roles', ['roles' => Role::withCount('users')->orderBy('rank')->get()]);
    }
}
