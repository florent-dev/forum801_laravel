<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Throttle writes to one per minute per user
        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinute()))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
        if ($user) {
            app(\App\Services\SecurityService::class)->trackIp($user, $request->ip());
        }

        return $next($request);
    }
}
