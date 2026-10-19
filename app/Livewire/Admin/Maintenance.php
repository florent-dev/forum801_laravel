<?php

namespace App\Livewire\Admin;

use App\Services\MaintenanceService;
use Illuminate\Support\Carbon;
use Livewire\Component;

class Maintenance extends Component
{
    public string $message = '';

    public string $until = '';

    public bool $allowModerators = false;

    public string $scheduledAt = '';

    public function mount(MaintenanceService $maintenance): void
    {
        $this->authorize('admin');
        $state = $maintenance->state();
        $this->message = (string) $state['message'];
        $this->allowModerators = (bool) $state['allow_moderators'];
        $this->until = $state['until'] ? Carbon::parse($state['until'])->format('Y-m-d\TH:i') : '';
        $this->scheduledAt = $state['scheduled_at'] ? Carbon::parse($state['scheduled_at'])->format('Y-m-d\TH:i') : '';
    }

    public function enable(MaintenanceService $maintenance): void
    {
        $this->authorize('admin');
        $this->validate([
            'message' => ['nullable', 'string', 'max:1000'],
            'until' => ['nullable', 'date', 'after:now'],
        ]);
        $maintenance->enable(auth()->user(), $this->message ?: null, $this->until ? Carbon::parse($this->until) : null, $this->allowModerators);
        $this->dispatch('toast', message: __('Maintenance mode is on. Visitors now see the maintenance page.'), type: 'info');
    }

    public function disable(MaintenanceService $maintenance): void
    {
        $this->authorize('admin');
        $maintenance->disable(auth()->user());
        $this->dispatch('toast', message: __('Maintenance mode is off. The forum is open again.'));
    }

    public function regenerateSecret(MaintenanceService $maintenance): void
    {
        $this->authorize('admin');
        $maintenance->regenerateSecret(auth()->user());
        $this->dispatch('toast', message: __('New staff link created. The old one no longer works.'), type: 'info');
    }

    public function schedule(MaintenanceService $maintenance): void
    {
        $this->authorize('admin');
        $this->validate([
            'scheduledAt' => ['nullable', 'date', 'after:now'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        $maintenance->schedule(auth()->user(), $this->scheduledAt ? Carbon::parse($this->scheduledAt) : null, $this->message ?: null);
        $this->dispatch('toast', message: $this->scheduledAt ? __('Upcoming maintenance announced.') : __('Announcement removed.'));
    }

    public function render(MaintenanceService $maintenance)
    {
        return view('livewire.admin.maintenance', ['state' => $maintenance->state(), 'accessUrl' => $maintenance->accessUrl()]);
    }
}
