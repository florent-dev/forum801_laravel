<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\User;
use App\Services\MessageService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConversationView extends Component
{
    #[Locked]
    public int $conversationId;

    public int $limit = 30;

    public string $body = '';

    public string $inviteName = '';

    public function mount(Conversation $conversation): void
    {
        $this->authorize('view', $conversation);
        $this->conversationId = $conversation->id;
    }

    protected function conversation(): Conversation
    {
        return Conversation::findOrFail($this->conversationId);
    }

    public function loadOlder(): void
    {
        $this->limit += 30;
    }

    public function send(MessageService $messages): void
    {
        $conversation = $this->conversation();
        $this->authorize('reply', $conversation);
        $this->validate(['body' => ['required', 'string', 'max:10000']]);
        $message = $messages->reply(auth()->user(), $conversation, $this->body);
        $this->reset('body');
        $this->dispatch('draft-sent', key: 'pm-'.$conversation->id);
        $this->dispatch('scroll-to-message', id: $message->id);
    }

    public function invite(MessageService $messages): void
    {
        $conversation = $this->conversation();
        $this->authorize('reply', $conversation);
        $this->validate(['inviteName' => ['required', 'exists:users,name']], ['inviteName.exists' => __('No user with that name.')]);
        $invited = User::where('name', $this->inviteName)->first();
        if ($invited->isBlockedWith(auth()->user())) {
            $this->addError('inviteName', __('You cannot add this user.'));

            return;
        }
        $messages->addParticipant($conversation, $invited);
        $this->reset('inviteName');
        $this->dispatch('close-modal', name: 'invite');
        $this->dispatch('toast', message: __('User added to the conversation.'));
    }

    public function leave(MessageService $messages)
    {
        $conversation = $this->conversation();
        $this->authorize('view', $conversation);
        $messages->leave($conversation, auth()->user());
        session()->flash('toast', __('You left the conversation.'));

        return $this->redirect(route('messages'), navigate: true);
    }

    public function render(MessageService $messages)
    {
        $conversation = $this->conversation();
        $this->authorize('view', $conversation);
        $total = $conversation->messages()->count();
        $items = $conversation->messages()->with('user.roles')->latest('id')->limit($this->limit)->get()->reverse();
        $messages->markRead($conversation, auth()->user());

        return view('livewire.conversation-view', [
            'conversation' => $conversation->load('activeParticipants.roles'),
            'items' => $items,
            'hasOlder' => $total > $this->limit,
        ]);
    }
}
