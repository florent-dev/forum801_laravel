<?php

namespace App\Livewire;

use App\Models\Section;
use App\Models\Thread;
use App\Services\ForumService;
use App\Services\MarkupRenderer;
use Livewire\Component;

class NewThread extends Component
{
    use Concerns\BuildsPoll;

    public Section $section;

    public string $title = '';

    public string $body = '';

    public string $prefix = '';

    public bool $showPreview = false;

    public function mount(Section $section): void
    {
        $this->authorize('createThread', $section);
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
    }

    public function save(ForumService $forum)
    {
        $this->authorize('createThread', $this->section);
        $rules = [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'body' => ['required', 'string', 'min:2', 'max:'.\App\Support\ForumSettings::maxPostLength()],
            'prefix' => ['nullable', 'in:'.implode(',', array_keys(Thread::PREFIXES))],
        ];
        $data = $this->validate($rules + $this->pollRules(), [], $this->pollAttributes());
        $poll = $this->pollPayload();

        $thread = $forum->createThread(auth()->user(), $this->section, trim($data['title']), $data['body'], $this->prefix ?: null, $poll);
        $this->dispatch('draft-sent', key: 'new-'.$this->section->id);
        session()->flash('toast', __('Your thread is live!'));

        return $this->redirect($thread->url(), navigate: true);
    }

    public function render(MarkupRenderer $renderer)
    {
        return view('livewire.new-thread', [
            'preview' => $this->showPreview ? $renderer->render($this->body) : null,
            'prefixes' => array_map(fn ($label) => __($label), Thread::PREFIXES),
        ]);
    }
}
