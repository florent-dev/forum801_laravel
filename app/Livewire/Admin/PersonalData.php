<?php

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\PrivacyService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class PersonalData extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Locked]
    public ?int $userId = null;

    public bool $deletePosts = false;

    public string $confirmName = '';

    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function pick(int $id): void
    {
        $this->authorize('admin');
        $this->userId = User::findOrFail($id)->id;
        $this->reset('deletePosts', 'confirmName');
    }

    public function anonymize(PrivacyService $privacy): void
    {
        $this->authorize('admin');
        $this->resetErrorBag();
        $user = User::findOrFail($this->userId);
        abort_if($user->hasRole(Role::ADMINISTRATOR) || $user->id === auth()->id(), 403);
        if ($this->confirmName !== $user->name) {
            $this->addError('confirmName', __('Type the exact nickname to confirm.'));

            return;
        }
        $privacy->anonymize(auth()->user(), $user, $this->deletePosts);
        $this->reset('confirmName', 'deletePosts');
        $this->dispatch('toast', message: __('The account has been anonymized.'));
    }

    public function render()
    {
        $term = trim($this->search);
        $results = $term === '' ? collect() : User::query()
            ->where(fn ($q) => $q->where('name', 'ilike', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('email', 'ilike', '%'.addcslashes($term, '%_\\').'%')
                ->when(ctype_digit($term), fn ($q) => $q->orWhere('id', (int) $term)))
            ->orderBy('name')->limit(12)->get();
        $user = $this->userId ? User::withCount(['posts', 'threads'])->find($this->userId) : null;

        return view('livewire.admin.personal-data', ['results' => $results, 'picked' => $user]);
    }
}
