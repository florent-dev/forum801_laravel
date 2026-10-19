# Conventions

- **Language:** code, comments and identifiers in English. Comments are one line and explain *why*.
- **Thin edges:** controllers and Livewire components validate input and delegate. Business rules live in services.
- **Authorization twice:** a policy or gate check in the component or controller, and again in the service.
- **Logging:** every moderation or admin action calls `ModerationLog::record()`. Security-relevant events call `SecurityService::record()`.
- **Mandatory reasons:** hiding and moderating posts, sanctions, bans and IP bans always require a reason.
- **No hard deletes for content:** threads and posts are soft deleted; the `deleted_by` column records who did it.
- **Denormalized counters:** call `Thread::refreshStats()` after changing post visibility.
- **User-visible strings:** always `__()` or `trans_choice()`. Toasts go through the `toast` browser event.
- **Confirmations:** `$confirm()` (styled dialog), never `wire:confirm` or `confirm()`.
- **Raw HTML:** only output `body_html`, `description_html` and other sanitized fields with `{!! !!}`. Everything else is escaped.
- **Tests:** every new permission or rule gets a Pest test, including the "forbidden" case.
