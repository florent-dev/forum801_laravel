# Features and their code

## Reading

| Feature | Code |
|---|---|
| Sections list, stats, online users, guild entries | `Livewire\SectionList`, `livewire/section-list.blade.php` |
| Thread list (sort, pages, unread markers, counter colors, bulk moderation) | `Livewire\ThreadList` |
| Thread view (posts, polling banner, reactions, author menu, moderation, polls, reply) | `Livewire\ThreadView`, `livewire/thread-view.blade.php` |
| Resume where you stopped, "new messages" divider, all-read → bottom | `ForumController::thread()` + `resumePage()`, `ThreadView::$readMarkId`, `public/js/forum-nav.js` |
| Post permalinks with the right page and highlighted target | `ForumController::post()` (`?p=` parameter), `ThreadView::$highlightPostId` |
| Search modal and results | `partials/search-modal.blade.php`, `ForumController::search()`, `SearchService` |
| RSS | `FeedController` (`/feed.rss`, `/s/{section}/feed.rss`) |

### Polling

Lists, header counters and threads use `wire:poll.20s`. In a thread, `ThreadView::checkNew()` counts posts above `ceilingId` and only re-renders when the count changes (`skipRender`). New posts never appear on their own; the banner calls `showNew()`.

## Writing

| Feature | Code |
|---|---|
| Markdown editor, toolbar, emoji, drafts | `components/editor.blade.php`, `Alpine.data('draft')` in the layout |
| Sanitizing, color tags, mentions | `MarkupRenderer`, `config/purifier.php` (`forum` profile) |
| @mention autocomplete | `FeedController::users()` (`/api/users`) and the autocomplete in `forum-nav.js` |
| New thread with prefix and poll | `Livewire\NewThread` + `Concerns\BuildsPoll` + `partials/poll-builder.blade.php` |
| Polls attached to posts | `Poll`, `PollPolicy`, `CommunityToolsService::vote()`, `partials/poll.blade.php` |
| Edit history | `post_revisions`, `ForumService::editPost()` |

## Community

| Feature | Code |
|---|---|
| Notifications | `Notifications\ForumActivity` (stores a translation key and its parameters), `HeaderBar`, `/notifications` |
| Following threads | `thread_subscriptions`, `ForumService::subscribe()` |
| Private messages | `MessageService`, `Livewire\Inbox`, `Livewire\ConversationView` |
| Friends, ignore and block lists | `FriendService`, `CommunityToolsService`, `Livewire\FriendsPage`, `Livewire\ProfileActions` |
| Bookmarks | `bookmarks` table, `/bookmarks` |
| Profiles | `ProfileController::show()`, `forum/profile.blade.php` |
| Settings | `ProfileController` (`/settings/{tab}`), `forum/settings.blade.php` |
| Guilds | `GuildService`, `GuildPolicy`, `Livewire\GuildDirectory`, `GuildPage`, `GuildManage` |
| Communities and languages | `config/forum.php`, `Support\Community`, `CommunityController`, `SetLocale` |
| Guided tours and help | `partials/tour.blade.php`, `Alpine.data('forumTour')`, `forum/help.blade.php` |

## Moderation and administration

| Feature | Code |
|---|---|
| Reports sidebar and review page | `Livewire\ReportList`, `Livewire\ReportPanel`, `ForumController::report()` |
| Deleted threads, moderation log | `Livewire\ModerationQueue` |
| Hide or moderate a post (mandatory reason), delete, restore, bulk | `ModerationService`, `ThreadView::askReason()/confirmReason()/bulk()` |
| Sanctions | `SanctionService`, `Livewire\SanctionModal` |
| IP information | `Livewire\IpInfoModal` (`view-ips` gate) |
| Admin: users, sections, roles, security | `Livewire\Admin\*` |
| Security events, IP bans, suspicious activity | `SecurityService`, `BlockBannedIp`, `Livewire\Admin\Security`, `forum:prune-security` |
| Email confirmation | `MustVerifyEmail` on `User`, `Auth\VerificationController` |
| Registration captcha and honeypot | `CaptchaService` (GD image, one-time code in the session), `/captcha.png`, `partials/captcha.blade.php`, `FORUM_CAPTCHA` |
| Maintenance mode | `MaintenanceService`, `MaintenanceMode` middleware, `Livewire\Admin\Maintenance`, `forum:maintenance`, `errors/maintenance.blade.php` |
| Forum settings: registration on/off, captcha on/off, posts and messages per minute, max post length, guilds per user, edit time window | `Support\ForumSettings` (key `forum_settings` in `settings`, defaults from `config/forum.php`), `Livewire\Admin\ForumSettingsPage`; read by `CaptchaService`, `AuthModal`, `AuthController`, `ForumService::throttle()`, `MessageService::throttle()`, `User::canJoinMoreGuilds()`, `PostPolicy::update()`, `components/editor` |
| Word filter (block, replace with `***`, publish and report) | `WordFilter` model, `WordFilterService::apply()` called by `ForumService` (titles, posts, own edits) and `MessageService` (subjects, messages; “report” does nothing there), `Livewire\Admin\WordFilters`. Reports from the filter have `reporter_id = null` |
| System health | `HealthService` (versions, database size, checks, `composer audit`, test email), `Livewire\Admin\Health`, scheduler heartbeat in `routes/console.php` |
| Moderator user file and staff notes | `Livewire\UserFile`, `UserNote`, `ModerationInsights::userSummary()`, route `moderation.users.show`; linked from the post author menu and the profile |
| Moderation search (author, IP prefix, text, status, section) | `ModerationQueue` tab `search`, `ModerationInsights::search()`, `partials/moderation-search` |
| Moderation statistics (handling times, per moderator, most reported sections and users, actions, sanctions) | `ModerationQueue` tab `stats`, `ModerationInsights::stats()`, `partials/moderation-stats` |
| Personal data: export, anonymize | `PrivacyService::export()/anonymize()`, `DataExportController` (`settings.export` for oneself, `admin.privacy.export`), `Livewire\Admin\PersonalData` |
