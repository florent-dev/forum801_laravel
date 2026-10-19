<?php

namespace App\Livewire;

use App\Models\Guild;
use App\Services\GuildService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GuildPage extends Component
{
    #[Locked]
    public int $guildId;

    public string $applicationMessage = '';

    public function mount(Guild $guild): void
    {
        $this->guildId = $guild->id;
    }

    protected function guild(): Guild
    {
        return Guild::findOrFail($this->guildId);
    }

    public function join(GuildService $guilds)
    {
        $guild = $this->guild();
        $this->validate(['applicationMessage' => [$guild->recruitment === 'application' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $application = $guilds->join(auth()->user(), $guild, $this->applicationMessage ?: null);
        $this->reset('applicationMessage');
        $this->dispatch('close-modal', name: 'apply-guild');
        if ($application) {
            $this->dispatch('toast', message: __('Application sent! The guild officers will answer soon.'));

            return null;
        }
        session()->flash('toast', __('Welcome to :name!', ['name' => $guild->name]));

        return $this->redirect(route('guilds.show', $guild), navigate: true);
    }

    public function leave(GuildService $guilds)
    {
        $guilds->leave(auth()->user(), $this->guild());
        session()->flash('toast', __('You left the guild.'));

        return $this->redirect(route('guilds.index'), navigate: true);
    }

    public function render()
    {
        $guild = $this->guild();
        $me = auth()->user();

        return view('livewire.guild-page', [
            'guild' => $guild,
            'members' => $guild->members()->with('user.roles')->orderByRaw("case role when 'owner' then 0 when 'officer' then 1 else 2 end")->orderBy('created_at')->get(),
            'myRole' => $me?->guildRole($guild),
            'pending' => $me ? $guild->applications()->where(['user_id' => $me->id, 'status' => 'pending'])->exists() : false,
            'section' => $guild->section,
            'threads' => $guild->section && $me?->can('view', $guild->section) ? $guild->section->threads()->with('lastPoster.roles')->orderByDesc('last_post_at')->limit(5)->get() : collect(),
        ]);
    }
}
