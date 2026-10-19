<?php

namespace App\Livewire\Admin;

use App\Services\HealthService;
use Livewire\Component;
use Throwable;

class Health extends Component
{
    public string $mailTo = '';

    public function mount(): void
    {
        $this->authorize('admin');
        $this->mailTo = (string) auth()->user()->email;
    }

    public function sendTestMail(HealthService $health): void
    {
        $this->authorize('admin');
        $this->validate(['mailTo' => ['required', 'email:rfc', 'not_regex:/[\r\n]/', 'max:255']]);
        try {
            $health->sendTestMail($this->mailTo);
            $this->dispatch('toast', message: __('Test email sent to :email.', ['email' => $this->mailTo]));
        } catch (Throwable $e) {
            $this->addError('mailTo', __('Sending failed: :error', ['error' => \Illuminate\Support\Str::limit($e->getMessage(), 200)]));
        }
    }

    public function runAudit(HealthService $health): void
    {
        $this->authorize('admin');
        $health->audit();
        $this->dispatch('toast', message: __('Security audit finished.'), type: 'info');
    }

    public function render(HealthService $health)
    {
        return view('livewire.admin.health', [
            'versions' => $health->versions(),
            'db' => $health->database(),
            'checks' => $health->checks(),
            'health' => $health,
        ]);
    }
}
