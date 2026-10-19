# Forum801

A web forum in the spirit of the old Atelier801 Flash forum. Built with Laravel 11, Livewire 3 (with Alpine) and PostgreSQL 16.

## Setup

```bash
composer install
cp .env.example .env   # then set DB_* (PostgreSQL)
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Tests use a separate PostgreSQL database, `jessica_test` (see `phpunit.xml`):

```bash
./vendor/bin/pest
```

## Demo accounts (password: `password`)

To make your own account administrator, register on the forum, then run `php artisan forum:make-admin <nickname> --verify-email`.

> [!WARNING]
> These accounts are for local development only. Their passwords are public, including the administrator's. Never run `DemoSeeder` on a public server, and never import a development database there. `php artisan migrate --seed` skips it when `APP_ENV=production`, so set that value in production (see [docs/operations.md](docs/operations.md)). If the demo data ever reached a public server, delete those accounts or change their passwords right away.

| Nickname    | Role |
|-------------|------|
| admin       | administrator |
| senior      | senior_moderator |
| modo        | moderator of *Discussions* |
| privatemod  | private_moderator (all private sections) |
| owner       | section_owner of *Bugs* |
| alice, bob, charlie | member |

The demo data includes:
- **Discussions** (English): 4 pages of threads.
- **"Introduce yourself!"**: a pinned thread with 6 pages of posts.
- A French community with 6 sections.
- Open reports, a private conversation, and some notifications.

## Features

**Reading**
- 3-panel layout: sections or threads on the left, content on the right; stacks on mobile.
- Lists update without a page reload. The left list keeps its page and sort while you open threads.
- Threads are sorted pinned first, then by activity. You can also sort by newest, replies or views.
- Unread markers, "first unread »" jump links, mark section / everything as read.
- Thread view counter and permalinks that open the right page.
- An image lightbox and a highlighted target post.

**Posting**
- Markdown editor with toolbar, emoji, colors, preview and Ctrl+Enter to send.
- Unsent drafts are kept in the browser.
- Posts can quote, @mention and react.
- Output is sanitized.

**Live updates**
- Every 20 s, a "N new posts" banner appears; new posts are never inserted while you read.
- The same 20 s poll updates the counters: unread, messages, notifications and reports.

**Community**
- Follow threads; notifications for replies, mentions, quotes, messages and moderation.
- Private messages (group conversations, add a user, leave).
- Profiles with tabs, stats and online status; who's online and forum stats.

**Languages**
- Each section belongs to a community (`config/forum.php`). The flag menu switches the community for the session; it is never saved on the account.
- The interface language is separate: a user setting (Settings › Language or the 🌐 menu), detected from the browser at registration or on a first visit.
- Every community stays reachable by URL and through search ("All communities").

**Moderation**
- **Per post:** clicking a post's author opens a menu: profile, message, quote, edit, report, plus hide-with-reason, delete/restore and ban for moderators.
- **Per thread:** a moderation menu to lock, pin, rename, move, delete or restore. Deleted threads and posts are soft-deleted (kept in the database) and can be restored.
- **Bulk:** selection mode in the thread list (delete, restore, lock, pin) and inside a thread (hide, unhide, delete, restore).
- **Moderation page:** reports queue, deleted threads (bulk restore), and the moderation log.
- **Admin:** users (search, roles, timed bans with a reason), sections (community, visibility, icon, order), role labels and colors.

**Maintenance**
- Admin › Maintenance or `php artisan forum:maintenance on|off|status`: visitors get a maintenance page, administrators (and optionally moderators) keep working, and an upcoming maintenance can be announced with a banner.

**Security**
- CSRF on all forms.
- Rate limits: posting, messaging, login, registration, search and settings.
- HTMLPurifier sanitizing; policies on every action.
- Redirects from notification links stay on this site.

## Where things are

| Concern | Location |
|---|---|
| Schema | `database/migrations/*` (tsvector + GIN search indexes on PostgreSQL) |
| Seeders | `RoleSeeder`, `SectionSeeder`, `DemoSeeder` (demo data, not in production) |
| Permissions | `app/Policies/*`, gates in `AppServiceProvider`, `role:` middleware |
| Moderation actions and logs | `app/Services/ModerationService.php` |
| Posting, notifications, read tracking | `app/Services/ForumService.php` |
| Private messages | `app/Services/MessageService.php` |
| Markdown → sanitized HTML, mentions | `app/Services/MarkupRenderer.php` + `forum` profile in `config/purifier.php` |
| Communities and locales | `config/forum.php`, `app/Support/Community.php`, `app/Http/Middleware/SetLocale.php`, `lang/*.json` |
| UI components | `app/Livewire/*`, `resources/views/livewire/*`, modal/editor components in `resources/views/components` |
| Styles | `public/css/forum.css` (no build step) |
| Tests | `tests/Feature/*` |
| Technical documentation | `docs/` (architecture, permissions, features, front end, i18n, operations, testing, conventions) |

## License and credits

Forum801 is an unofficial fan project, not affiliated with Atelier801. The code is under the MIT License.

The section icons, flags and lock icon belong to **Atelier801** and are not covered by the MIT License. Parts of the app are inspired by the old Flash forum used in Atelier801's services in 2010-2015. See [LICENSE](LICENSE) for the full credits.
