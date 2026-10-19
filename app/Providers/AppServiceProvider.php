<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\Report;
use App\Models\Role;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Localized short dates: only the time for today, the date otherwise
        \Illuminate\Support\Carbon::macro('shortDate', function () {
            /** @var \Illuminate\Support\Carbon $this */
            return $this->isToday() ? $this->isoFormat('LT') : $this->isoFormat('L');
        });
        \Illuminate\Support\Carbon::macro('shortDateTime', function () {
            /** @var \Illuminate\Support\Carbon $this */
            return $this->isToday() ? $this->isoFormat('LT') : $this->isoFormat('L LT');
        });

        Relation::enforceMorphMap([
            'user' => User::class,
            'section' => Section::class,
            'thread' => Thread::class,
            'post' => Post::class,
            'report' => Report::class,
            'role' => Role::class,
            'word_filter' => \App\Models\WordFilter::class,
            'user_note' => \App\Models\UserNote::class,
        ]);

        Gate::define('admin', fn (User $user) => $user->isAdmin() && ! $user->isBanned());

        // Anyone holding a moderation role may open the moderation queue
        Gate::define('access-moderation', fn (User $user) => ! $user->isBanned() && $user->roleAssignments()->contains(
            fn (Role $r) => in_array($r->name, [Role::MODERATOR, Role::SENIOR_MODERATOR, Role::PRIVATE_MODERATOR, Role::SECTION_OWNER, Role::ADMINISTRATOR], true)
        ));

        // Staff may sanction members ranked strictly below them
        Gate::define('sanction', fn (User $user, User $target) => $user->id !== $target->id
            && Gate::forUser($user)->allows('access-moderation')
            && $user->staffRank() > $target->staffRank());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('name')).'|'.$request->ip())
            ->response(function () use ($request) {
                app(\App\Services\SecurityService::class)->record('lockout', null, ['name' => (string) $request->input('name')]);

                return response(__('Too many login attempts. Please try again later.'), 429);
            }));

        // Authentication events feed the security log
        $security = fn () => app(\App\Services\SecurityService::class);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, fn ($e) => $security()->record('login', $e->user, ['remember' => $e->remember]));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, fn ($e) => $e->user ? $security()->record('logout', $e->user) : null);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, fn ($e) => $security()->record('login_failed', $e->user, ['name' => (string) ($e->credentials['name'] ?? '')]));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Registered::class, function ($e) use ($security) {
            $e->user->forceFill(['registration_ip' => request()->ip()])->saveQuietly();
            $security()->record('register', $e->user);
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Verified::class, fn ($e) => $security()->record('email_verified', $e->user));

        // Senior staff can see network addresses
        Gate::define('view-ips', fn (User $user) => ! $user->isBanned() && ($user->isAdmin() || $user->hasGlobalRole(Role::SENIOR_MODERATOR)));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
    }
}
