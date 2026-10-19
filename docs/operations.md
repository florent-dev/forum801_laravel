# Operations

## Requirements

PHP 8.3 with `pdo_pgsql`, `mbstring`, `intl`, `xml`; Composer 2; PostgreSQL 16. Redis is optional (cache and rate limits use the database store by default).

## Install

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# set DB_*, APP_URL, MAIL_* in .env
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=SectionSeeder --force   # optional starter sections
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### First administrator

Register an account on the forum as usual, then give it the administrator role:

```bash
php artisan forum:make-admin <nickname> --verify-email
```

- `--verify-email` also confirms the email address, which helps when mail is not configured yet.
- `--remove` takes the role away. It refuses to remove the last administrator.
- Both are recorded as security events (`admin_granted`, `admin_revoked`).
- Use the same command to recover access if no administrator can log in.

`DemoSeeder` only runs outside production (`DatabaseSeeder`).

## Environment

| Variable | Meaning |
|---|---|
| `APP_URL` | Absolute links in emails, notifications and RSS |
| `DB_*` | PostgreSQL connection |
| `MAIL_*` | Needed for email confirmation (`MAIL_MAILER=log` writes mails to the log in development) |
| `CACHE_STORE`, `SESSION_DRIVER` | `database` by default; `redis` recommended in production |
| `FORUM_MAX_GUILDS` | Default guilds per user (5); Admin › Forum settings overrides it |
| `FORUM_CAPTCHA` | Default for the registration captcha (`true`; needs the PHP `gd` extension with FreeType); Admin › Forum settings overrides it |

Registration, captcha, posting limits, guild limit and the edit time window are changed live in **Admin › Forum settings**. They are stored in the `settings` table (key `forum_settings`); values never saved there fall back to `config/forum.php`.
| `FORUM_SECURITY_RETENTION_DAYS` | Days before IPs and security events are purged (default 180) |

Behind a reverse proxy, configure trusted proxies (`bootstrap/app.php` → `$middleware->trustProxies(at: …)`) so `request()->ip()` is the visitor's address. IP tracking, IP bans and rate limits depend on it.

## Scheduler

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

| Task | Frequency |
|---|---|
| `forum:prune-security` | Daily: deletes old `security_events`, old `user_ips` and old `posts.ip` values |
| `heartbeat` | Every minute: stores the time of the last run, shown in Admin › System health |

**Admin › System health** shows versions, database size and largest tables, and checks the cache, the scheduler heartbeat, the queue, mail, debug mode, HTTPS, storage, disk space and pending migrations. It can send a test email and run `composer audit` (Composer must be installed on the server; the result is kept for a day).

## Personal data

- **Users** download their own data as JSON from Settings › Account (3 exports per hour).
- **Administrators** use **Admin › Personal data**:
  - download any account's data (logged as `data_export`);
  - anonymize an account (logged as `account_anonymize`). This removes the nickname (it becomes `deleted-<id>`), email, password, profile, IP addresses, friends, blocks, guild memberships (ownership goes to an officer or the oldest member, and an empty guild is closed), subscriptions, reactions, bookmarks, notifications and sessions.
  - Posts stay so threads remain readable, unless “also delete their posts” is ticked (soft delete).
  - Administrators must have their role removed first.

No queue worker is needed: notifications and emails are sent synchronously. If mail volume grows, make `ForumActivity` and `VerifyEmail` queued and run `php artisan queue:work`.

## Maintenance mode

Two complementary switches show the same forum-styled maintenance page (HTTP 503 with `Retry-After`):

| Switch | Use it for | Who still gets in |
|---|---|---|
| **Admin › Maintenance** or `php artisan forum:maintenance on --message="…" --until="2026-10-20 22:00" [--allow-moderators]` | Planned work while the app keeps running (moderation clean-up, data fixes) | Administrators, plus moderators when allowed; the login page stays open |
| `php artisan down --secret=<token>` | Deployments and anything that breaks the app itself | Only browsers that visited `/<token>` |

- `forum:maintenance off` / `status` complete the CLI.
- **Hidden staff access:** while the forum is closed, `/login` (showing and submitting the form) is blocked too. Turning maintenance on creates a secret link, `/staff/<token>`, shown in Admin › Maintenance (with Copy and Regenerate) and printed by `forum:maintenance on|status`. Opening it unlocks the login form for that browser session. Once logged in, administrators always get in, and moderators too when allowed. A wrong token returns 404; every use is logged (`maintenance_access`). The link stays the same when you update the maintenance settings and changes when you regenerate it or start a new maintenance.
- The CLI builds the link from `APP_URL`, so set it to the public address of the forum.
- The same page can announce an upcoming maintenance with a banner shown to everyone (`schedule`).
- State lives in the `settings` table (key `maintenance`), cached forever and cleared on change.
- Switching is recorded as a security event and in the moderation log.

## Deploying an update

```bash
php artisan down
git pull && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Backups

Back up the PostgreSQL database (`pg_dump -Fc`). Nothing else is stateful: there are no uploads, and avatars and emblems are URLs or bundled images.

## Security checklist

- `APP_DEBUG=false`, HTTPS only, `SESSION_SECURE_COOKIE=true`.
- Rate limits: login 5/min per nickname and IP, registration 10/h per IP, posting 5/min, messages 10/min, search 30/min, mention lookup 60/min, settings 6/min, email links 6/min, resend 3/min.
- Watch **Admin › Security** (failed logins, lockouts, ban evasion, shared addresses).
- IP bans accept single addresses and CIDR ranges. Banned addresses can read but not log in, register or post.
- Personal data: IPs and security events are purged after the retention period. Mention the retention in your privacy policy.
- Security headers (`SecurityHeaders` middleware): `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS on HTTPS. There is no CSP yet: Livewire/Alpine need `unsafe-eval`, so adding one needs care.
- Run `composer audit` regularly. Laravel 11 no longer gets security fixes; plan the upgrade to Laravel 12 (see QUESTIONS.md).
