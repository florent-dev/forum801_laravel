<?php

namespace App\Services;

use App\Models\IpBan;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SecurityService
{
    public function record(string $type, ?User $user = null, array $details = [], ?string $severity = null): SecurityEvent
    {
        $request = request();

        return SecurityEvent::create([
            'user_id' => $user?->id,
            'type' => $type,
            'severity' => $severity ?? (SecurityEvent::TYPES[$type] ?? 'info'),
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255) ?: null,
            'details' => $details ?: null,
        ]);
    }

    // Remember the address an account uses, at most once a minute per address
    public function trackIp(User $user, ?string $ip): void
    {
        if (! $ip || ! Cache::add("ip-seen:{$user->id}:{$ip}", true, 60)) {
            return;
        }
        $now = now();
        DB::table('user_ips')->upsert(
            [['user_id' => $user->id, 'ip' => $ip, 'hits' => 1, 'first_seen_at' => $now, 'last_seen_at' => $now]],
            ['user_id', 'ip'],
            ['hits' => DB::raw('user_ips.hits + 1'), 'last_seen_at' => $now]
        );
        if ($user->last_ip !== $ip) {
            $user->forceFill(['last_ip' => $ip])->saveQuietly();
        }
        $this->detectBanEvasion($user, $ip);
    }

    // Accounts that used one of the given addresses, except the given user
    public function sharedAccounts(User $user, int $limit = 50)
    {
        $ips = DB::table('user_ips')->where('user_id', $user->id)->pluck('ip');

        return User::whereIn('id', DB::table('user_ips')->whereIn('ip', $ips)->where('user_id', '!=', $user->id)->select('user_id'))
            ->limit($limit)->get();
    }

    public function accountsForIp(string $ip)
    {
        return DB::table('user_ips')
            ->join('users', 'users.id', '=', 'user_ips.user_id')
            ->where('user_ips.ip', $ip)
            ->orderByDesc('user_ips.last_seen_at')
            ->get(['users.id', 'users.name', 'user_ips.hits', 'user_ips.first_seen_at', 'user_ips.last_seen_at']);
    }

    // Ban lookups are cached per address; bumping the version invalidates them all
    public function isBanned(?string $ip): ?IpBan
    {
        $version = Cache::get('ip-ban-version', 0);

        return Cache::remember("ip-ban:{$version}:{$ip}", 60, fn () => IpBan::matching($ip) ?? false) ?: null;
    }

    public function forgetBanCache(): void
    {
        Cache::forever('ip-ban-version', Cache::get('ip-ban-version', 0) + 1);
    }

    // Raise an alert once a day when an account shares an address with a suspended one
    protected function detectBanEvasion(User $user, string $ip): void
    {
        if ($user->isBanned() || $user->isStaff()) {
            return;
        }
        $suspended = User::whereIn('id', DB::table('user_ips')->where('ip', $ip)->where('user_id', '!=', $user->id)->select('user_id'))
            ->get()->filter(fn (User $u) => $u->isBanned());
        if ($suspended->isNotEmpty() && Cache::add("evasion:{$user->id}:{$ip}", true, 86400)) {
            $this->record('ban_evasion_suspected', $user, ['shared_with' => $suspended->pluck('name')->all()]);
        }
    }
}
