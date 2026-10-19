<?php

namespace App\Livewire;

use App\Services\ForumService;
use Livewire\Component;

class MarkAllRead extends Component
{
    public function markAll(ForumService $forum)
    {
        abort_unless(auth()->check(), 403);
        $forum->markAllRead(auth()->user());
        session()->flash('toast', __('Everything is marked as read.'));

        return $this->redirect(route('unread'), navigate: true);
    }

    public function render()
    {
        return <<<'BLADE'
            <button type="button" class="icon-btn" wire:click="markAll">✓ Mark everything read</button>
        BLADE;
    }
}
