<?php

namespace App\Livewire;

use App\Models\ModerationLog;
use App\Models\User;
use App\Models\UserNote;
use App\Services\ModerationInsights;
use App\Services\SecurityService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

// Moderator file about one user: sanctions, reports, IPs and staff notes
class UserFile extends Component
{
    #[Locked]
    public int $userId;

    public string $note = '';

    public function mount(User $user): void
    {
        $this->authorize('access-moderation');
        $this->userId = $user->id;
    }

    public function addNote(): void
    {
        $this->authorize('access-moderation');
        $this->validate(['note' => ['required', 'string', 'min:2', 'max:2000']], [], ['note' => __('note')]);
        $note = UserNote::create(['user_id' => $this->userId, 'author_id' => auth()->id(), 'body' => trim($this->note)]);
        ModerationLog::record(auth()->user(), 'note.add', $note->user);
        $this->reset('note');
        $this->dispatch('toast', message: __('Note added.'));
    }

    public function deleteNote(int $id): void
    {
        $this->authorize('access-moderation');
        $note = UserNote::where('user_id', $this->userId)->findOrFail($id);
        abort_unless($note->author_id === auth()->id() || auth()->user()->can('admin'), 403);
        $note->delete();
        ModerationLog::record(auth()->user(), 'note.delete', $note->user);
    }

    // Sanctions are issued or revoked from the modal; refresh the history afterwards
    #[On('sanctions-updated')]
    public function refreshAfterSanction(): void
    {
    }

    public function render(ModerationInsights $insights, SecurityService $security)
    {
        $user = User::with('roles')->withCount(['threads', 'posts'])->findOrFail($this->userId);
        $me = auth()->user();
        $canSeeIps = $me->can('view-ips');

        return view('livewire.user-file', [
            'user' => $user,
            'summary' => $insights->userSummary($me, $user),
            'sanctions' => $user->sanctions()->with(['moderator', 'revoker'])->latest('id')->get(),
            'notes' => $user->staffNotes()->with('author.roles')->latest('id')->get(),
            'canSeeIps' => $canSeeIps,
            'ips' => $canSeeIps ? DB::table('user_ips')->where('user_id', $user->id)->orderByDesc('last_seen_at')->limit(10)->get() : collect(),
            'shared' => $canSeeIps ? $security->sharedAccounts($user, 30) : collect(),
        ]);
    }
}
