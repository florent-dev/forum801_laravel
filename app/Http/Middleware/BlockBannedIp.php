<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class BlockBannedIp
{
    // Banned addresses may read, but cannot log in, register or write anything
    public function handle(Request $request, Closure $next): Response
    {
        $writing = ! $request->isMethodSafe() || $request->routeIs('login', 'register');
        if ($writing && ($ban = app(SecurityService::class)->isBanned($request->ip()))) {
            if (Cache::add('banned-ip-logged:'.$request->ip(), true, 300)) {
                app(SecurityService::class)->record('banned_ip_blocked', $request->user(), ['path' => $request->path(), 'ban_id' => $ban->id]);
            }
            abort(403, __('Your network address is banned from posting: :reason', ['reason' => $ban->reason]));
        }

        return $next($request);
    }
}
