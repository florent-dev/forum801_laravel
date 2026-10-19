<?php

namespace App\Livewire;

use App\Models\Guild;
use App\Services\GuildService;
use App\Support\Community;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class GuildDirectory extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $community = '';

    #[Url]
    public bool $recruiting = false;

    public string $name = '';

    public string $tag = '';

    public string $newCommunity = '';

    public string $recruitment = 'application';

    public string $description = '';

    public function mount(): void
    {
        $this->community = $this->community ?: Community::current();
        $this->newCommunity = Community::current();
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'community', 'recruiting'], true)) {
            $this->resetPage();
        }
    }

    public function create(GuildService $guilds)
    {
        $this->authorize('create', Guild::class);
        $data = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[\pL\pN _\'\-]+$/u', Rule::unique('guilds', 'name')],
            'tag' => ['required', 'string', 'min:2', 'max:6', 'alpha_num'],
            'newCommunity' => ['required', 'in:'.implode(',', array_keys(Community::all()))],
            'recruitment' => ['required', 'in:'.implode(',', Guild::RECRUITMENT)],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $guild = $guilds->create(auth()->user(), [
            'name' => trim($data['name']), 'tag' => $data['tag'], 'community' => $data['newCommunity'],
            'recruitment' => $data['recruitment'], 'description' => $data['description'] ?: null,
        ]);
        session()->flash('toast', __('Your guild :name is born! 🏰', ['name' => $guild->name]));

        return $this->redirect(route('guilds.show', $guild), navigate: true);
    }

    public function render()
    {
        $guilds = Guild::withCount('members')
            ->when($this->community !== 'all', fn ($q) => $q->where('community', $this->community))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.$this->search.'%')->orWhere('tag', 'ilike', '%'.$this->search.'%')))
            ->when($this->recruiting, fn ($q) => $q->where('recruitment', '!=', 'closed'))
            ->orderByDesc('members_count')->orderBy('name')
            ->paginate(24);

        return view('livewire.guild-directory', ['guilds' => $guilds, 'myGuilds' => auth()->user()?->guilds() ?? collect()]);
    }

    public function paginationView(): string
    {
        return 'partials.pager';
    }
}
