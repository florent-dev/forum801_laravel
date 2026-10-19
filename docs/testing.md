# Testing

```bash
./vendor/bin/pest                    # whole suite
./vendor/bin/pest tests/Feature/PollTest.php
./vendor/bin/pest --filter="sanction"
```

Tests run against PostgreSQL database `jessica_test` (`phpunit.xml`), refreshed for every test (`RefreshDatabase`). `RoleSeeder` runs before each test (`tests/Pest.php`).

## Helpers (`tests/Pest.php`)

| Helper | Purpose |
|---|---|
| `userWithRole($role, ?$section)` | Verified user with `member` plus the given (optionally scoped) role |
| `threadIn($section, ?$author)` | Thread with a first post and correct counters |
| `sanction($user, $type, $reason, $until)` | Adds an active sanction |

Factories: `User::factory()->unverified()`, `->banned()`, `Section::factory()->private()`, `Thread::factory()`, `Post::factory()`.

## Layout of the suite

| Folder or file | Covers |
|---|---|
| `Roles/*` | One file per role: what it can and cannot do |
| `PagesSmokeTest` | Every page renders for guests, members and admins; 404s; slug redirects |
| Feature files | One per area: polls, guilds, sanctions, security, search, social, soft delete, … |

## Pitfalls

- `Livewire::actingAs()` keeps the user for the rest of the test; call `auth()->logout()` before checking guest behaviour.
- After a 403 inside a Livewire test, start a new `Livewire::test()` instead of chaining further calls.
- `assertSee` also sees the sidebar (latest thread titles in tooltips). Prefer `viewData()` or `assertViewHas()` for exact result checks.
- CSRF is disabled in tests by Laravel; rely on the framework for it.

## Browser checks

Visual checks so far used headless Chrome through the DevTools protocol (a small Node script that logs in, clicks, takes screenshots and reports console errors). Run one after any change to Alpine code: Alpine errors only show up in the browser.
