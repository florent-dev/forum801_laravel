<?php

use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Personal data retention: old addresses and events are deleted
Artisan::command('forum:prune-security {--days=}', function () {
    $days = (int) ($this->option('days') ?: config('forum.security_retention_days'));
    $limit = now()->subDays($days);
    $events = SecurityEvent::where('created_at', '<', $limit)->delete();
    $ips = DB::table('user_ips')->where('last_seen_at', '<', $limit)->delete();
    $posts = DB::table('posts')->whereNotNull('ip')->where('created_at', '<', $limit)->update(['ip' => null]);
    $this->info("Pruned {$events} events, {$ips} addresses and {$posts} post addresses older than {$days} days.");
})->purpose('Delete IP addresses and security events past the retention period');

Schedule::command('forum:prune-security')->daily();

// Same switch as Admin > Maintenance, for the command line
Artisan::command('forum:maintenance {action : on, off or status} {--message=} {--until=} {--allow-moderators}', function (App\Services\MaintenanceService $maintenance) {
    match ($this->argument('action')) {
        'on' => $maintenance->enable(null, $this->option('message'), $this->option('until') ? Illuminate\Support\Carbon::parse($this->option('until')) : null, (bool) $this->option('allow-moderators')),
        'off' => $maintenance->disable(null),
        'status' => null,
        default => $this->error('Use on, off or status.'),
    };
    $state = $maintenance->state();
    $this->info($state['enabled'] ? 'Maintenance mode is ON'.($state['until'] ? ' until '.$state['until'] : '') : 'The forum is open');
    if ($url = $maintenance->accessUrl()) {
        $this->line('Staff link (log in during maintenance): '.$url);
    }
})->purpose('Turn the forum maintenance mode on or off');

// Lets Admin › System health tell whether the cron entry runs
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever(\App\Services\HealthService::HEARTBEAT, now()->toIso8601String()))->everyMinute()->name('heartbeat');

// First administrator on a fresh install, or recovery when no admin can log in
Artisan::command('forum:make-admin {name : Nickname of an existing account} {--remove : Take the administrator role away instead} {--verify-email : Also mark the email address as confirmed}', function () {
    $user = App\Models\User::whereRaw('lower(name) = lower(?)', [$this->argument('name')])->first();
    if (! $user) {
        $this->error('No account named "'.$this->argument('name').'". Register it on the forum first.');

        return 1;
    }
    $role = App\Models\Role::where('name', App\Models\Role::ADMINISTRATOR)->first();
    if (! $role) {
        $this->error('Roles are missing. Run: php artisan db:seed --class=RoleSeeder --force');

        return 1;
    }
    $isAdmin = $user->roles()->whereKey($role->id)->whereNull('role_user.scope_id')->exists();

    if ($this->option('remove')) {
        if (! $isAdmin) {
            $this->warn("{$user->name} is not an administrator.");

            return 0;
        }
        $others = DB::table('role_user')->where('role_id', $role->id)->whereNull('scope_id')->where('user_id', '!=', $user->id)->count();
        if ($others === 0) {
            $this->error("{$user->name} is the last administrator. Make someone else administrator first.");

            return 1;
        }
        $user->removeRole(App\Models\Role::ADMINISTRATOR);
        app(App\Services\SecurityService::class)->record('admin_revoked', $user, ['via' => 'console'], 'warning');
        $this->info("{$user->name} is no longer an administrator.");

        return 0;
    }

    if ($isAdmin) {
        $this->info("{$user->name} is already an administrator.");
    } else {
        if (! $user->hasRole(App\Models\Role::MEMBER)) {
            $user->assignRole(App\Models\Role::MEMBER);
        }
        $user->assignRole(App\Models\Role::ADMINISTRATOR);
        app(App\Services\SecurityService::class)->record('admin_granted', $user, ['via' => 'console'], 'warning');
        $this->info("{$user->name} is now an administrator.");
    }
    if ($this->option('verify-email') && ! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        $this->info('Email address marked as confirmed.');
    } elseif (! $user->hasVerifiedEmail()) {
        $this->warn('The email address is not confirmed yet: confirm it, or run again with --verify-email, before posting.');
    }
    if ($user->isBanned()) {
        $this->warn('This account is suspended: revoke the sanction before using it.');
    }

    return 0;
})->purpose('Give (or take away) the administrator role for an existing account');
