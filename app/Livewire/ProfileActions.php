<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\CommunityToolsService;
use App\Services\FriendService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProfileActions extends Component
{
    #[Locked]
    public int $userId;

    public function mount(User $user): void
    {
        $this->userId = $user->id;
    }

    protected function other(): User
    {
        return User::findOrFail($this->userId);
    }

    public function addFriend(FriendService $friends): void
    {
        abort_unless(auth()->check(), 403);
        $status = $friends->request(auth()->user(), $this->other())->accepted_at ? 'friends' : 'sent';
        $this->dispatch('toast', message: $status === 'friends' ? __('You are now friends!') : __('Friend request sent.'));
    }

    public function removeFriend(FriendService $friends): void
    {
        abort_unless(auth()->check(), 403);
        $friends->remove(auth()->user(), $this->other());
        $this->dispatch('toast', message: __('Done.'), type: 'info');
    }

    public function toggleIgnore(CommunityToolsService $tools): void
    {
        abort_unless(auth()->check(), 403);
        $ignored = $tools->toggleIgnore(auth()->user(), $this->other());
        $this->dispatch('toast', message: $ignored ? __('You will no longer see this user’s posts and messages.') : __('User removed from your ignore list.'), type: 'info');
    }

    public function toggleBlock(CommunityToolsService $tools): void
    {
        abort_unless(auth()->check(), 403);
        $blocked = $tools->toggleBlock(auth()->user(), $this->other());
        $this->dispatch('toast', message: $blocked ? __('User blocked. They can no longer contact you.') : __('User unblocked.'), type: 'info');
    }

    public function render(FriendService $friends)
    {
        $me = auth()->user();
        $other = $this->other();

        return view('livewire.profile-actions', [
            'other' => $other,
            'self' => $me?->id === $other->id,
            'status' => $me && $me->id !== $other->id ? $friends->status($me, $other) : null,
            'ignoring' => $me ? $me->ignores($other) : false,
            'blocking' => $me ? $me->hasBlocked($other) : false,
            'blockedByOther' => $me ? $other->hasBlocked($me) : false,
        ]);
    }
}
