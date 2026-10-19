<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = app(MaintenanceService::class);
        if (! $maintenance->isEnabled() || $maintenance->canBypass($request->user()) || $this->isOpen($request)) {
            return $next($request);
        }
        $state = $maintenance->state();
        $retry = $state['until'] ? max(60, now()->diffInSeconds(\Illuminate\Support\Carbon::parse($state['until']), false)) : 3600;

        return response()->view('errors.maintenance', ['state' => $state], 503)->header('Retry-After', (string) (int) $retry);
    }

    // Always open: health check, logout and the secret staff link.
    // The login form (GET and POST, matched by path as POST /login has no name) only after the secret link was used.
    protected function isOpen(Request $request): bool
    {
        if ($request->is('up', 'logout', 'staff/*')) {
            return true;
        }

        return $request->is('login') && $request->session()->get('maintenance_access') === app(MaintenanceService::class)->secret();
    }
}
