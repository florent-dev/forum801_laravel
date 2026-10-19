<?php

namespace App\Services;

use App\Models\ModerationLog;
use App\Models\Role;
use App\Models\Sanction;
use App\Models\User;
use App\Notifications\ForumActivity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SanctionService
{
    // Longest sanction in days a moderator may give, null means unlimited
    public static function maxDays(User $moderator): ?int
    {
        if ($moderator->isAdmin()) {
            return null;
        }

        return $moderator->hasGlobalRole(Role::SENIOR_MODERATOR) ? 30 : 7;
    }

    public function apply(User $moderator, User $target, string $type, ?int $days, string $reason): Sanction
    {
        Gate::forUser($moderator)->authorize('sanction', $target);
        if (! in_array($type, Sanction::TYPES, true)) {
            throw ValidationException::withMessages(['type' => __('Unknown sanction type.')]);
        }
        $max = static::maxDays($moderator);
        if ($type !== Sanction::WARNING) {
            if ($days === null && $max !== null) {
                throw ValidationException::withMessages(['days' => __('Only administrators can give permanent sanctions.')]);
            }
            if ($days !== null && $max !== null && $days > $max) {
                throw ValidationException::withMessages(['days' => __('You can sanction for :days days at most.', ['days' => $max])]);
            }
        }

        $sanction = Sanction::create([
            'user_id' => $target->id,
            'moderator_id' => $moderator->id,
            'type' => $type,
            'reason' => $reason,
            'expires_at' => $type === Sanction::WARNING || $days === null ? null : now()->addDays($days),
        ]);
        $target->flushSanctionCache();
        if ($type === Sanction::SUSPENSION) {
            $target->forceFill(['status' => 'suspended'])->saveQuietly();
        }

        ModerationLog::record($moderator, 'user.'.$type, $target, null, array_filter([
            'name' => $target->name,
            'until' => $sanction->expires_at?->format('Y-m-d H:i'),
            'reason' => $reason,
        ]));

        [$key, $params] = match ($type) {
            Sanction::WARNING => ['You received a warning: :reason', ['reason' => $reason]],
            Sanction::MUTE => $sanction->expires_at
                ? ['You are muted until :date: :reason', ['date' => $sanction->expires_at->isoFormat('L LT'), 'reason' => $reason]]
                : ['You are muted: :reason', ['reason' => $reason]],
            Sanction::SUSPENSION => $sanction->expires_at
                ? ['Your account is suspended until :date: :reason', ['date' => $sanction->expires_at->isoFormat('L LT'), 'reason' => $reason]]
                : ['Your account is suspended: :reason', ['reason' => $reason]],
        };
        $target->notify(new ForumActivity('moderation', $key, $params, route('profile', $target), $moderator));

        return $sanction;
    }

    public function revoke(User $moderator, Sanction $sanction): void
    {
        Gate::forUser($moderator)->authorize('sanction', $sanction->user);
        if ($sanction->revoked_at) {
            return;
        }
        $sanction->update(['revoked_at' => now(), 'revoked_by' => $moderator->id]);
        $sanction->user->flushSanctionCache();
        // The status column mirrors whether a suspension is still running
        if ($sanction->type === Sanction::SUSPENSION && ! $sanction->user->isBanned()) {
            $sanction->user->forceFill(['status' => 'active'])->saveQuietly();
        }
        ModerationLog::record($moderator, 'sanction.revoke', $sanction->user, null, ['name' => $sanction->user->name, 'type' => $sanction->type]);
    }
}
