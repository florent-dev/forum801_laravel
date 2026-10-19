# Permissions

## Forum roles

Roles live in `roles` and are assigned through `role_user`. A row with `scope_type = 'section'` and a `scope_id` limits the role to one section. Constants are in `App\Models\Role`.

| Role | Scope | Can |
|---|---|---|
| member | global | Post, reply, react, report, message (after email confirmation) |
| moderator | section | Moderate the assigned section(s) |
| senior_moderator | global | Moderate every **public** section; see IP information |
| private_moderator | global or one private section | Moderate private sections (all of them when global) |
| section_owner | section | Moderate the section, edit its description and icon, add or remove its moderators |
| administrator | global | Everything, including the admin pages and the security tools |

Each role has a `rank`. `User::staffRank()` uses it to stop moderators from sanctioning peers or superiors.

## Where checks happen

| Layer | File | Purpose |
|---|---|---|
| Gates | `AppServiceProvider` | `admin`, `access-moderation` (any moderation role), `sanction` (rank based), `view-ips` |
| Policies | `app/Policies/*` | Per-model abilities (see below) |
| Middleware | `EnsureRole` (`role:administrator`), `can:` | Route-level guards |
| Services | `Gate::forUser($user)->authorize()` | Defence in depth when services are called directly |

### Main policy abilities

| Policy | Abilities |
|---|---|
| `SectionPolicy` | `view`, `moderate` (central rule), `update`, `manageModerators`, `createThread` |
| `ThreadPolicy` | `view` (deleted threads: moderators only), `reply`, `update`, `moderate`, `lock`, `pin`, `move`, `delete`, `restore`, `solve` |
| `PostPolicy` | `view`, `viewContent` (moderated text), `viewHistory`, `update`, `hide`, `delete`, `restore`, `react`, `report` |
| `ReportPolicy` | `resolve` |
| `ConversationPolicy` | `view`, `create`, `reply` (blocked users cannot write) |
| `PollPolicy` | `vote`, `close` |
| `GuildPolicy` | `create`, `join`, `update` (owner), `manageMembers` (owner or officer), `delete` |
| `UserPolicy` | `update`, `manage` |

`SectionPolicy::moderate()` is the single source of truth for "can moderate here". Thread, post and report abilities delegate to it.

### Moderation tools

| Tool | Who | Scope |
|---|---|---|
| User file (`/moderation/users/{id}`) | `access-moderation` | Sanctions are global. Post counts and reports only cover the sections the viewer moderates. IPs and linked accounts need `view-ips`. |
| Staff notes | `access-moderation` can read and add | Only the note's author or an administrator can delete it. Users never see notes, and their data export leaves them out. |
| Moderation search | `access-moderation` | Posts in moderated sections only, hidden and deleted posts included. The IP filter and IP column need `view-ips`; for others the IP filter is ignored. |
| Moderation statistics | `access-moderation` | Reports and log entries from moderated sections; administrators also see entries without a section. |

## Taking part

`User::canParticipate()` must be true to create threads, reply, react, vote, edit your own posts or send messages. It requires:

- a confirmed email (`MustVerifyEmail`),
- no active suspension,
- no active mute.

Muted members can still read and report. Suspended members can only read.

## Sanctions

`SanctionService` applies `warning`, `mute` or `suspension`. The longest sanction each rank may give:

| Moderator | Longest sanction |
|---|---|
| moderator, private_moderator, section_owner | 7 days |
| senior_moderator | 30 days |
| administrator | permanent |

Sanctions expire on their own (`Sanction::scopeActive()`). Revoking one keeps it in the history. `users.status` mirrors whether a suspension is running.

## Guild roles

Guild roles are separate from forum roles and stored in `guild_members.role`.

| Guild role | Can |
|---|---|
| owner | Edit the guild page and settings, promote and demote officers, transfer ownership, disband the guild, everything an officer can do |
| officer | Moderate the guild forum section (it counts as `moderate` for that section only), accept or decline applications, remove plain members |
| member | Read and post in the guild forum |

Officers are **not** official moderators. They get no access to the moderation page, reports, sanctions or IPs. Administrators and global private moderators keep authority over guild forums.

## User-to-user controls

| Control | Effect |
|---|---|
| Ignore (`user_ignores`) | Collapses the user's posts for you; they cannot message you or send friend requests |
| Block (`user_blocks`) | Everything ignore does, plus: no notifications at all from them (`ForumActivity::via`), no reactions on your posts, no writing in conversations you are in, no adding you to conversations. Friendship removed. Staff cannot be blocked |
| Mention policy | `everyone`, `friends` or `nobody` (`User::acceptsMentionFrom`) |
| Hide online status | `hide_online`; `User::isOnline()` and `scopeVisiblyOnline()` honour it |
