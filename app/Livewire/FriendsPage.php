<?php

namespace App\Livewire;

use App\Models\Friendship;
use App\Models\User;
use App\Services\CommunityToolsService;
use App\Services\FriendService;
use Livewire\Attributes\Url;
use Livewire\Component;

class FriendsPage extends Component
{
    #[Url]
    public string $tab = 'friends';

    public string $addName = '';

    public function add(FriendService $friends): void
    {
        $this->validate(['addName' => ['required', 'exists:users,name', 'not_in:'.auth()->user()->name]], ['addName.exists' => __('No user with that name.'), 'addName.not_in' => __('You cannot add yourself.')]);
        $friends->request(auth()->user(), User::where('name', $this->addName)->first());
        $this->reset('addName');
        $this->dispatch('toast', message: __('Friend request sent.'));
    }

    public function accept(int $friendshipId, FriendService $friends): void
    {
        $friends->accept(auth()->user(), Friendship::where('friend_id', auth()->id())->findOrFail($friendshipId));
        $this->dispatch('toast', message: __('You are now friends!'));
    }

    public function remove(int $userId, FriendService $friends): void
    {
        $friends->remove(auth()->user(), User::findOrFail($userId));
        $this->dispatch('toast', message: __('Done.'), type: 'info');
    }

    public function unignore(int $userId, CommunityToolsService $tools): void
    {
        $other = User::findOrFail($userId);
        if (auth()->user()->ignores($other)) {
            $tools->toggleIgnore(auth()->user(), $other);
        }
        $this->dispatch('toast', message: __('User removed from your ignore list.'), type: 'info');
    }

    public function unblock(int $userId, CommunityToolsService $tools): void
    {
        $other = User::findOrFail($userId);
        if (auth()->user()->hasBlocked($other)) {
            $tools->toggleBlock(auth()->user(), $other);
        }
        $this->dispatch('toast', message: __('User unblocked.'), type: 'info');
    }

    public function render()
    {
        $me = auth()->user();

        return view('livewire.friends-page', [
            'friends' => User::with('roles')->whereIn('id', $me->friendIds())->orderByDesc('last_seen_at')->get(),
            'received' => Friendship::with('requester.roles')->where('friend_id', $me->id)->whereNull('accepted_at')->latest()->get(),
            'sent' => Friendship::with('recipient.roles')->where('user_id', $me->id)->whereNull('accepted_at')->latest()->get(),
            'ignored' => $me->ignoredUsers()->with('roles')->orderBy('name')->get(),
            'blocked' => $me->blockedUsers()->with('roles')->orderBy('name')->get(),
        ]);
    }
}
