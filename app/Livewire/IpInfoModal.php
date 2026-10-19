<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class IpInfoModal extends Component
{
    public ?int $userId = null;

    public ?int $postId = null;

    // Opened with $dispatch('open-ip-info', { userId: 1, postId: 2 })
    #[On('open-ip-info')]
    public function open(int $userId, ?int $postId = null): void
    {
        $this->authorize('view-ips');
        $this->userId = $userId;
        $this->postId = $postId;
        $this->dispatch('open-modal', name: 'ip-info');
    }

    public function render(SecurityService $security)
    {
        $user = $this->userId && auth()->user()?->can('view-ips') ? User::find($this->userId) : null;

        return view('livewire.ip-info-modal', [
            'user' => $user,
            'postIp' => $this->postId ? Post::withTrashed()->whereKey($this->postId)->value('ip') : null,
            'ips' => $user ? DB::table('user_ips')->where('user_id', $user->id)->orderByDesc('last_seen_at')->limit(20)->get() : collect(),
            'shared' => $user ? $security->sharedAccounts($user, 30) : collect(),
        ]);
    }
}
