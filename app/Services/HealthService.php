<?php

namespace App\Services;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

// Facts and checks shown in Admin › System health
class HealthService
{
    public const HEARTBEAT = 'scheduler:heartbeat';

    public function versions(): array
    {
        return [
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Livewire' => $this->package('livewire/livewire'),
            'PostgreSQL' => rescue(fn () => Str::of(DB::scalar('show server_version'))->before(' ')->toString(), '?', false),
            __('Environment') => app()->environment(),
            __('Server') => php_uname('s').' '.php_uname('r'),
        ];
    }

    public function database(): array
    {
        $tables = collect(DB::select("select relname as name, pg_total_relation_size(relid) as size, n_live_tup as rows from pg_stat_user_tables order by pg_total_relation_size(relid) desc limit 8"));

        return [
            'size' => (int) DB::scalar('select pg_database_size(current_database())'),
            'connections' => (int) DB::scalar('select count(*) from pg_stat_activity where datname = current_database()'),
            'tables' => $tables->map(fn ($t) => ['name' => $t->name, 'size' => (int) $t->size, 'rows' => (int) $t->rows])->all(),
            'counts' => [
                __('Users') => DB::table('users')->count(),
                __('Threads') => DB::table('threads')->count(),
                __('Posts') => DB::table('posts')->count(),
                __('Private messages') => DB::table('messages')->count(),
            ],
        ];
    }

    // Each check: label, level (ok, warning, error) and a short explanation
    public function checks(): array
    {
        $checks = [];
        $add = function (string $label, string $level, string $detail) use (&$checks) {
            $checks[] = compact('label', 'level', 'detail');
        };

        $add(__('Database'), 'ok', __('Connected (:driver).', ['driver' => DB::getDriverName()]));

        try {
            $probe = Str::random(8);
            Cache::put('health:probe', $probe, 10);
            Cache::get('health:probe') === $probe
                ? $add(__('Cache'), 'ok', __('Read and write work (:driver).', ['driver' => config('cache.default')]))
                : $add(__('Cache'), 'error', __('The value written could not be read back.'));
        } catch (Throwable $e) {
            $add(__('Cache'), 'error', $e->getMessage());
        }

        $beat = Cache::get(self::HEARTBEAT);
        if (! $beat) {
            $add(__('Scheduler'), 'warning', __('Never seen running. Add “php artisan schedule:run” to the server crontab, every minute.'));
        } else {
            $ago = now()->diffInMinutes(\Illuminate\Support\Carbon::parse($beat));
            $add(__('Scheduler'), abs($ago) > 5 ? 'warning' : 'ok', __('Last run :when.', ['when' => \Illuminate\Support\Carbon::parse($beat)->diffForHumans()]));
        }

        $failed = DB::table('failed_jobs')->count();
        $pending = DB::table('jobs')->count();
        $add(__('Queue'), $failed ? 'warning' : 'ok', __('Driver :driver, :pending waiting, :failed failed.', ['driver' => config('queue.default'), 'pending' => $pending, 'failed' => $failed]));

        $mailer = config('mail.default');
        $add(__('Mail'), in_array($mailer, ['log', 'array'], true) ? 'warning' : 'ok', in_array($mailer, ['log', 'array'], true)
            ? __('Mailer “:mailer”: emails are not really sent.', ['mailer' => $mailer])
            : __('Mailer “:mailer”, sender :from.', ['mailer' => $mailer, 'from' => config('mail.from.address')]));

        if (config('app.debug')) {
            $add(__('Debug mode'), app()->isProduction() ? 'error' : 'warning', __('APP_DEBUG is on: error pages show internal details. Turn it off in production.'));
        } else {
            $add(__('Debug mode'), 'ok', __('Off.'));
        }

        $https = str_starts_with((string) config('app.url'), 'https://');
        $add(__('HTTPS'), $https ? 'ok' : (app()->isProduction() ? 'error' : 'warning'), $https ? __('The site address uses HTTPS.') : __('APP_URL does not start with https://.'));

        $writable = is_writable(storage_path('framework')) && is_writable(storage_path('logs'));
        $add(__('Storage'), $writable ? 'ok' : 'error', $writable ? __('Storage folders are writable.') : __('storage/ is not writable by the web server.'));

        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if ($free !== false && $total) {
            $ratio = $free / $total;
            $add(__('Disk space'), $ratio < .05 ? 'error' : ($ratio < .15 ? 'warning' : 'ok'), __(':free free of :total.', ['free' => $this->bytes($free), 'total' => $this->bytes($total)]));
        }

        $pendingMigrations = $this->pendingMigrations();
        $add(__('Migrations'), $pendingMigrations ? 'error' : 'ok', $pendingMigrations ? __(':count migrations not run yet: php artisan migrate', ['count' => $pendingMigrations]) : __('Up to date.'));

        $audit = Cache::get('health:audit');
        if ($audit) {
            $level = $audit['error'] ? 'warning' : ($audit['advisories'] ? 'error' : 'ok');
            $detail = $audit['error'] ?: ($audit['advisories']
                ? __(':count security advisories in dependencies: :packages', ['count' => $audit['advisories'], 'packages' => implode(', ', $audit['packages'])])
                : __('No known vulnerability in dependencies.'));
            $add(__('Dependencies'), $level, $detail.' · '.__('Checked :when.', ['when' => \Illuminate\Support\Carbon::parse($audit['at'])->diffForHumans()]));
        } else {
            $add(__('Dependencies'), 'warning', __('Not checked yet. Run the security audit below.'));
        }

        return $checks;
    }

    // composer audit against the lock file, kept for a day
    public function audit(): array
    {
        $result = ['at' => now()->toIso8601String(), 'advisories' => 0, 'packages' => [], 'error' => null];
        try {
            $composer = trim((string) Process::run('command -v composer')->output()) ?: null;
            if (! $composer) {
                $result['error'] = __('Composer is not installed on this server.');
            } else {
                $run = Process::path(base_path())->timeout(90)->env(['COMPOSER_HOME' => storage_path('framework/composer')])
                    ->run([$composer, 'audit', '--locked', '--format=json', '--no-interaction']);
                $json = json_decode($run->output(), true);
                if (! is_array($json)) {
                    $result['error'] = __('The audit could not run: :error', ['error' => Str::limit(trim($run->errorOutput()) ?: 'no output', 200)]);
                } else {
                    $advisories = collect($json['advisories'] ?? []);
                    $result['advisories'] = $advisories->flatten(1)->count();
                    $result['packages'] = $advisories->keys()->all();
                }
            }
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
        }
        Cache::put('health:audit', $result, now()->addDay());

        return $result;
    }

    public function sendTestMail(string $to): void
    {
        Mail::raw(__('This is a test email from :name. If you can read it, sending emails works.', ['name' => config('app.name')]), function ($m) use ($to) {
            $m->to($to)->subject(__('Test email from :name', ['name' => config('app.name')]));
        });
    }

    public function bytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $i ? 1 : 0).' '.$units[$i];
    }

    protected function package(string $name): string
    {
        return rescue(fn () => ltrim((string) InstalledVersions::getPrettyVersion($name), 'v'), '?', false);
    }

    protected function pendingMigrations(): int
    {
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));

        return $files->diff(DB::table('migrations')->pluck('migration'))->count();
    }
}
