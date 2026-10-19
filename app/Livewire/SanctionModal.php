<?php

namespace App\Livewire;

use App\Models\Sanction;
use App\Models\User;
use App\Services\SanctionService;
use Livewire\Attributes\On;
use Livewire\Component;

class SanctionModal extends Component
{
    public const DURATIONS = [1 => '1 day', 3 => '3 days', 7 => '1 week', 14 => '2 weeks', 30 => '1 month', 90 => '3 months', 365 => '1 year'];

    public ?int $targetId = null;

    public string $type = Sanction::WARNING;

    public string $days = '1';

    public string $reason = '';

    // Opened from anywhere with $dispatch('open-sanction', { userId: 1 })
    #[On('open-sanction')]
    public function open(int $userId): void
    {
        $target = User::findOrFail($userId);
        $this->authorize('sanction', $target);
        $this->targetId = $target->id;
        $this->reset('type', 'days', 'reason');
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'sanction');
    }

    public function apply(SanctionService $sanctions): void
    {
        $target = User::findOrFail((int) $this->targetId);
        $this->authorize('sanction', $target);
        $this->validate([
            'type' => ['required', 'in:'.implode(',', Sanction::TYPES)],
            'days' => ['required', 'in:'.implode(',', [...array_keys(self::DURATIONS), 'forever'])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $sanctions->apply(auth()->user(), $target, $this->type, $this->days === 'forever' ? null : (int) $this->days, $this->reason);
        $this->reset('reason');
        $this->dispatch('sanctions-updated');
        $this->dispatch('toast', message: __('Sanction applied to :name.', ['name' => $target->name]));
    }

    public function revoke(int $sanctionId, SanctionService $sanctions): void
    {
        $sanction = Sanction::with('user')->where('user_id', $this->targetId)->findOrFail($sanctionId);
        $sanctions->revoke(auth()->user(), $sanction);
        $this->dispatch('sanctions-updated');
        $this->dispatch('toast', message: __('Sanction lifted.'));
    }

    public function render()
    {
        $target = $this->targetId ? User::find($this->targetId) : null;
        $max = auth()->check() ? SanctionService::maxDays(auth()->user()) : 0;
        $durations = collect(self::DURATIONS)
            ->filter(fn ($label, $days) => $max === null || $days <= $max)
            ->map(fn ($label) => __($label))
            ->all();
        if ($max === null) {
            $durations['forever'] = __('Permanently');
        }

        return view('livewire.sanction-modal', [
            'target' => $target,
            'history' => $target ? $target->sanctions()->with(['moderator', 'revoker'])->latest('id')->limit(15)->get() : collect(),
            'durations' => $durations,
            'types' => [Sanction::WARNING => __('Warning'), Sanction::MUTE => __('Mute (cannot post or message)'), Sanction::SUSPENSION => __('Suspension (account blocked)')],
        ]);
    }
}
