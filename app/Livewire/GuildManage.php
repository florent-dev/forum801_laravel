<?php

namespace App\Livewire;

use App\Models\Guild;
use App\Models\GuildApplication;
use App\Models\GuildMember;
use App\Services\GuildService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class GuildManage extends Component
{
    #[Locked]
    public int $guildId;

    #[Url]
    public string $tab = 'members';

    public string $tag = '';

    public string $emblem = '';

    public string $recruitment = 'application';

    public bool $forumPublic = false;

    public string $description = '';

    public function mount(Guild $guild): void
    {
        $this->authorize('manageMembers', $guild);
        $this->guildId = $guild->id;
        $this->tag = $guild->tag;
        $this->emblem = (string) $guild->emblem;
        $this->recruitment = $guild->recruitment;
        $this->forumPublic = $guild->forum_public;
        $this->description = (string) $guild->description;
        if (! in_array($this->tab, ['members', 'applications', 'page'], true)) {
            $this->tab = 'members';
        }
    }

    protected function guild(): Guild
    {
        return Guild::findOrFail($this->guildId);
    }

    protected function member(int $id): GuildMember
    {
        return GuildMember::with('user')->where('guild_id', $this->guildId)->findOrFail($id);
    }

    public function savePage(GuildService $guilds): void
    {
        $this->validate([
            'tag' => ['required', 'string', 'min:2', 'max:6', 'alpha_num'],
            'emblem' => ['nullable', 'string', 'max:255', 'regex:#^(img/sections/[A-Za-z0-9_\-]+\.png|https://\S+)$#'],
            'recruitment' => ['required', 'in:'.implode(',', Guild::RECRUITMENT)],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $guilds->update(auth()->user(), $this->guild(), [
            'tag' => $this->tag, 'emblem' => $this->emblem, 'recruitment' => $this->recruitment,
            'forum_public' => $this->forumPublic, 'description' => $this->description,
        ]);
        $this->dispatch('toast', message: __('Guild page saved.'));
    }

    public function decide(int $applicationId, bool $accept, GuildService $guilds): void
    {
        $application = GuildApplication::with(['user', 'guild'])->where('guild_id', $this->guildId)->findOrFail($applicationId);
        $guilds->decide(auth()->user(), $application, $accept);
        $this->dispatch('toast', message: $accept ? __(':name joined the guild.', ['name' => $application->user->name]) : __('Application declined.'), type: $accept ? 'success' : 'info');
    }

    public function setRole(int $memberId, string $role, GuildService $guilds): void
    {
        $guilds->setRole(auth()->user(), $this->guild(), $this->member($memberId), $role);
        $this->dispatch('toast', message: __('Role updated.'));
    }

    public function transfer(int $memberId, GuildService $guilds)
    {
        $guilds->transferOwnership(auth()->user(), $this->guild(), $this->member($memberId));
        session()->flash('toast', __('Ownership transferred.'));

        return $this->redirect(route('guilds.show', $this->guild()), navigate: true);
    }

    public function kick(int $memberId, GuildService $guilds): void
    {
        $member = $this->member($memberId);
        $guilds->kick(auth()->user(), $this->guild(), $member);
        $this->dispatch('toast', message: __(':name was removed from the guild.', ['name' => $member->user->name]), type: 'info');
    }

    public function deleteGuild(GuildService $guilds)
    {
        $guilds->delete(auth()->user(), $this->guild());
        session()->flash('toast', __('The guild was disbanded.'));

        return $this->redirect(route('guilds.index'), navigate: true);
    }

    public function render()
    {
        $guild = $this->guild();
        $this->authorize('manageMembers', $guild);

        return view('livewire.guild-manage', [
            'guild' => $guild,
            'isOwner' => auth()->user()->can('update', $guild),
            'myRole' => auth()->user()->guildRole($guild),
            'members' => $guild->members()->with('user.roles')->orderByRaw("case role when 'owner' then 0 when 'officer' then 1 else 2 end")->orderBy('created_at')->get(),
            'applications' => $guild->applications()->with('user.roles')->where('status', 'pending')->oldest()->get(),
            'icons' => collect(glob(public_path('img/sections/*.png')))->map(fn ($p) => 'img/sections/'.basename($p)),
        ]);
    }
}
