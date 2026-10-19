<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\User;
use App\Services\MessageService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Inbox extends Component
{
    use WithPagination;

    #[Url(as: 'to')]
    public string $recipients = '';

    public string $subject = '';

    public string $body = '';

    public function mount(): void
    {
        // Opening /messages?to=name pops the compose dialog
        if ($this->recipients !== '') {
            $this->dispatch('open-modal', name: 'compose');
        }
    }

    public function send(MessageService $messages)
    {
        $this->authorize('create', Conversation::class);
        $this->validate([
            'recipients' => ['required', 'string', 'max:300'],
            'subject' => ['required', 'string', 'min:2', 'max:120'],
            'body' => ['required', 'string', 'min:1', 'max:10000'],
        ]);
        $names = collect(preg_split('/[\s,;]+/', $this->recipients))->filter()->unique()->take(10);
        $users = User::whereIn('name', $names)->where('id', '!=', auth()->id())->get();
        $missing = $names->diff($users->pluck('name'))->reject(fn ($n) => $n === auth()->user()->name);
        if ($missing->isNotEmpty()) {
            $this->addError('recipients', __('Unknown user(s): :names', ['names' => $missing->implode(', ')]));

            return null;
        }
        $blocking = $users->filter(fn ($u) => $u->ignores(auth()->user()) || $u->isBlockedWith(auth()->user()));
        if ($blocking->isNotEmpty()) {
            $this->addError('recipients', __(':names does not accept your messages.', ['names' => $blocking->pluck('name')->implode(', ')]));

            return null;
        }
        if ($users->isEmpty()) {
            $this->addError('recipients', __('Add at least one other user.'));

            return null;
        }
        $conversation = $messages->start(auth()->user(), $users, trim($this->subject), $this->body);
        $this->reset('recipients', 'subject', 'body');

        return $this->redirect(route('messages.show', $conversation), navigate: true);
    }

    public function render()
    {
        $user = auth()->user();
        $conversations = $user->conversations()
            ->wherePivotNull('left_at')
            ->with(['activeParticipants.roles', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderByDesc('conversations.last_message_at')
            ->paginate(20);

        return view('livewire.inbox', compact('conversations'));
    }

    public function paginationView(): string
    {
        return 'partials.pager';
    }
}
