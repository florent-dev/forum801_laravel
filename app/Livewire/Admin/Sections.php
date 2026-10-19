<?php

namespace App\Livewire\Admin;

use App\Models\ModerationLog;
use App\Models\Section;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Sections extends Component
{
    public ?int $editingId = null;

    public array $form = [];

    public function mount(): void
    {
        $this->authorize('admin');
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'slug' => '', 'description' => '', 'icon' => 'img/sections/Discussions.png', 'community' => \App\Support\Community::current(), 'visibility' => 'public', 'position' => (int) Section::max('position') + 1];
    }

    public function edit(int $id): void
    {
        $section = Section::findOrFail($id);
        $this->editingId = $id;
        $this->form = $section->only(['name', 'slug', 'community', 'description', 'icon', 'visibility', 'position']);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorize('admin');
        $this->form['slug'] = Str::slug($this->form['slug'] ?: $this->form['name']);
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:80'],
            'form.slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('sections', 'slug')->ignore($this->editingId)],
            'form.description' => ['nullable', 'string', 'max:500'],
            'form.icon' => ['nullable', 'string', 'max:255', 'regex:#^(img/sections/[A-Za-z0-9_\-]+\.png|https://\S+)$#'],
            'form.community' => ['required', 'in:'.implode(',', array_keys(\App\Support\Community::all()))],
            'form.visibility' => ['required', 'in:public,private'],
            'form.position' => ['required', 'integer', 'min:0'],
        ])['form'];

        $section = $this->editingId ? tap(Section::findOrFail($this->editingId))->update($data) : Section::create($data);
        ModerationLog::record(auth()->user(), $this->editingId ? 'section.update' : 'section.create', $section, $section->id, ['name' => $section->name]);
        $this->dispatch('toast', message: __('Section :name saved.', ['name' => $section->name]));
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('admin');
        $section = Section::findOrFail($id);
        // Threads are never destroyed: a section must be emptied (moved) before it can go
        if ($section->threads()->withTrashed()->exists()) {
            $this->dispatch('toast', message: __('Move this section’s threads to another section before deleting it.'), type: 'error');

            return;
        }
        ModerationLog::record(auth()->user(), 'section.delete', $section, null, ['name' => $section->name]);
        $section->delete();
    }

    public function render()
    {
        return view('livewire.admin.sections', [
            'sections' => Section::withCount('threads')->orderBy('community')->orderBy('position')->get(),
            'icons' => collect(glob(public_path('img/sections/*.png')))->map(fn ($p) => 'img/sections/'.basename($p)),
            'communities' => \App\Support\Community::all(),
        ]);
    }
}
