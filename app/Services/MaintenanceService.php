<?php

namespace App\Services;

use App\Models\ModerationLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MaintenanceService
{
    // State shape: enabled, message, until, allow_moderators, scheduled_at, by, since
    public function state(): array
    {
        return Setting::get('maintenance', []) + [
            'enabled' => false, 'message' => null, 'until' => null, 'allow_moderators' => false, 'scheduled_at' => null,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) $this->state()['enabled'];
    }

    public function canBypass(?User $user): bool
    {
        if (! $user || $user->isBanned()) {
            return false;
        }

        // Administrators always get in; other staff only when allowed
        return $user->isAdmin() || ($this->state()['allow_moderators'] && $user->isStaff());
    }

    public function enable(?User $by, ?string $message, ?Carbon $until, bool $allowModerators): void
    {
        Setting::put('maintenance', [
            'enabled' => true,
            'message' => $message,
            'until' => $until?->toIso8601String(),
            'allow_moderators' => $allowModerators,
            'scheduled_at' => null,
            'by' => $by?->name,
            'since' => now()->toIso8601String(),
            // Hidden staff access: /staff/{secret} opens the login form for that browser
            'secret' => $this->state()['enabled'] && ! empty($this->state()['secret']) ? $this->state()['secret'] : Str::random(32),
        ]);
        $this->log($by, 'maintenance_on', ['message' => $message, 'until' => $until?->toDateTimeString()]);
    }

    public function secret(): ?string
    {
        return $this->state()['secret'] ?? null;
    }

    public function accessUrl(): ?string
    {
        return $this->isEnabled() && $this->secret() ? route('maintenance.access', $this->secret()) : null;
    }

    public function regenerateSecret(?User $by): void
    {
        Setting::put('maintenance', array_merge($this->state(), ['secret' => Str::random(32)]));
        $this->log($by, 'maintenance_secret_regenerated');
    }

    public function checkSecret(string $token): bool
    {
        return $this->isEnabled() && $this->secret() && hash_equals($this->secret(), $token);
    }

    public function disable(?User $by): void
    {
        Setting::put('maintenance', ['enabled' => false] + array_intersect_key($this->state(), ['allow_moderators' => 1]));
        $this->log($by, 'maintenance_off');
    }

    // Announce a coming maintenance with a banner, without closing the forum
    public function schedule(?User $by, ?Carbon $at, ?string $message): void
    {
        Setting::put('maintenance', array_merge($this->state(), [
            'scheduled_at' => $at?->toIso8601String(),
            'message' => $message ?? $this->state()['message'],
        ]));
        $this->log($by, 'maintenance_scheduled', ['at' => $at?->toDateTimeString()]);
    }

    protected function log(?User $by, string $type, array $details = []): void
    {
        app(SecurityService::class)->record($type, $by, array_filter($details), 'warning');
        if ($by) {
            ModerationLog::record($by, $type, $by, null, array_filter($details));
        }
    }
}
