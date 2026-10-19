<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    // Usage: role:administrator or role:senior_moderator,administrator
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user && ! $user->isBanned(), 403);

        foreach ($roles as $role) {
            if ($user->hasGlobalRole($role)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
