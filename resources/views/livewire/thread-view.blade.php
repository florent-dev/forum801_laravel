<div class="thread-view" style="display:flex;flex-direction:column;min-height:0;flex:1" wire:poll.20s="checkNew">
    <div class="panel-head thread-head">
        <h1 class="thread-title thread-flags">
            @if ($thread->is_pinned)<span title="{{ __('Pinned') }}">📌</span>@endif
            @if ($thread->is_locked)<img src="{{ asset('img/locked.png') }}" class="lock-icon" alt="{{ __('locked') }}" title="{{ __('Locked') }}">@endif
            @if ($thread->solution_post_id)<span class="prefix solved">✅ {{ __('Solved') }}</span>@endif
            @if ($thread->prefix)<span class="prefix prefix-{{ $thread->prefix }}">{{ $prefixes[$thread->prefix] ?? $thread->prefix }}</span>@endif
            {{ $thread->title }}
        </h1>
        <div style="display:flex;gap:6px;align-items:center">
            @can('update', $thread)
                <div class="dropdown" x-data="{ open: false }" x-on:click.outside="open = false">
                    <button type="button" class="icon-btn" x-on:click="open = !open" title="{{ __('Thread prefix') }}">🏷️ {{ __('Prefix') }} ▾</button>
                    <div class="dropdown-menu" x-show="open" x-cloak x-transition.origin.top.right x-on:click="open = false">
                        <button type="button" wire:click="setPrefix('')">— {{ __('None') }}</button>
                        @foreach ($prefixes as $key => $label)
                            <button type="button" wire:click="setPrefix('{{ $key }}')"><span class="prefix prefix-{{ $key }}">{{ $label }}</span></button>
                        @endforeach
                    </div>
                </div>
            @endcan
            @auth
                <button type="button" class="icon-btn {{ $subscribed ? 'on' : '' }}" wire:click="toggleSubscription" title="{{ $subscribed ? __('Stop following') : __('Follow: get notified of new replies') }}">{{ $subscribed ? '★ '.__('Following') : '☆ '.__('Follow') }}</button>
            @endauth
            @if ($canModerate)
                <div class="dropdown" x-data="{ open: false }" x-on:click.outside="open = false">
                    <button type="button" class="btn small" x-on:click="open = !open" style="color:var(--warn)">🛡️ {{ __('Moderate') }} ▾</button>
                    <div class="dropdown-menu" x-show="open" x-cloak x-transition.origin.top.right x-on:click="open = false">
                        <button type="button" wire:click="toggleLock">{{ $thread->is_locked ? '🔓 '.__('Unlock thread') : '🔒 '.__('Lock thread') }}</button>
                        <button type="button" wire:click="togglePin">{{ $thread->is_pinned ? '📍 '.__('Unpin thread') : '📌 '.__('Pin thread') }}</button>
                        <button type="button" x-on:click="$dispatch('open-modal', 'rename-thread')">✏️ {{ __('Rename…') }}</button>
                        <button type="button" x-on:click="$dispatch('open-modal', 'move-thread')">📦 {{ __('Move…') }}</button>
                        <div class="dropdown-sep"></div>
                        <button type="button" wire:click="toggleSelectMode">☑️ {{ $selectMode ? __('Stop selecting posts') : __('Select posts…') }}</button>
                        <button type="button" wire:click="toggleShowDeleted">👻 {{ $showDeleted ? __('Hide deleted posts') : __('Show deleted posts') }}</button>
                        <div class="dropdown-sep"></div>
                        @if ($thread->trashed())
                            <button type="button" wire:click="restoreThread">♻️ {{ __('Restore thread') }}</button>
                        @else
                            <button type="button" class="danger-item" x-on:click="$confirm(@js(__('The thread disappears for members. Moderators can still restore it from the moderation page.')), () => $wire.deleteThread(), { title: @js(__('Delete this thread?')), label: @js(__('Delete')) })">🗑️ {{ __('Delete thread') }}</button>
                        @endif
                    </div>
                </div>
            @endif
            {{ $posts->links() }}
        </div>
    </div>

    <div class="scroll" id="thread-scroll" @if ($readMarkId && $readMarkId >= (int) $thread->last_post_id) data-all-read @endif>
        @if ($thread->trashed())
            <div class="deleted-banner">
                🗑️ {{ __('This thread was deleted :when.', ['when' => $thread->deleted_at->diffForHumans()]) }} {{ $thread->deleter ? __('Deleted by :name.', ['name' => $thread->deleter->name]) : '' }} {{ __('Only moderators can see it.') }}
                @if ($canModerate)<button type="button" class="btn small primary" wire:click="restoreThread">♻️ {{ __('Restore') }}</button>@endif
            </div>
        @endif

        @if ($selectMode)
            <div class="bulk-bar">
                <span>{!! __(':count selected', ['count' => '<strong>'.count($selected).'</strong>']) !!}</span>
                <button type="button" class="btn small" wire:click="bulk('hide')">{{ __('Hide') }}</button>
                <button type="button" class="btn small" wire:click="bulk('moderate')">{{ __('Moderate') }}</button>
                <button type="button" class="btn small" wire:click="bulk('unmoderate')">{{ __('Remove note') }}</button>
                <button type="button" class="btn small" wire:click="bulk('unhide')">{{ __('Unhide') }}</button>
                <button type="button" class="btn small danger" x-on:click="$confirm(@js(__('Delete the selected posts? They can be restored later.')), () => $wire.bulk('delete'), { title: @js(__('Delete posts')), label: @js(__('Delete')) })">{{ __('Delete') }}</button>
                <button type="button" class="btn small primary" wire:click="bulk('restore')">{{ __('Restore') }}</button>
                <span class="spacer"></span>
                <button type="button" class="linkbtn" x-on:click="$wire.set('selected', [...document.querySelectorAll('.post-check')].map(c => c.value))">{{ __('Select page') }}</button>
                <button type="button" class="linkbtn" wire:click="$set('selected', [])">{{ __('Clear') }}</button>
                <button type="button" class="linkbtn" wire:click="toggleSelectMode">{{ __('Done') }}</button>
            </div>
        @endif

        @if ($newCount > 0)
            <button type="button" class="new-posts-banner" wire:click="showNew">
                {{ trans_choice(':count new post — click to show|:count new posts — click to show', $newCount) }}
            </button>
        @endif

        @if ($thread->solution && $posts->onFirstPage() && $thread->solution->id !== $posts->first()?->id)
            <a class="solution-box" href="{{ route('posts.show', $thread->solution) }}" wire:navigate>
                <strong>✅ {{ __('Solution by :name', ['name' => $thread->solution->user?->name ?? '—']) }}</strong>
                <span>{{ $thread->solution->excerpt(200) }}</span>
            </a>
        @endif

        @php($dividerShown = false)
        @foreach ($posts as $post)
            @php($author = $post->user)
            @if ($readMarkId && ! $dividerShown && $post->id > $readMarkId)
                @php($dividerShown = true)
                <div class="unread-divider" id="new-messages"><span>{{ __('New messages since your last visit') }}</span></div>
            @endif
            <article class="thread-post {{ $post->is_hidden ? 'hidden-post' : '' }} {{ $post->trashed() ? 'deleted-post' : '' }} {{ $post->id === $highlightPostId ? $highlightStyle.'-post' : '' }} {{ in_array($post->id, $bookmarked) ? 'bookmarked-post' : '' }} {{ $post->moderated_at ? 'moderated-post' : '' }} {{ $thread->solution_post_id === $post->id ? 'solution-post' : '' }} {{ in_array((string) $post->id, array_map('strval', $selected), true) ? 'selected-post' : '' }}" id="post-{{ $post->id }}" wire:key="p{{ $post->id }}">
                @if ($selectMode)
                    <label class="post-select" title="{{ __('Select this post') }}"><input type="checkbox" class="post-check" value="{{ $post->id }}" wire:model.live="selected"></label>
                @endif
                <div class="authorcolumn dropdown" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                    <button type="button" class="author-trigger" x-on:click="open = !open" aria-haspopup="true" :aria-expanded="open" title="{{ __('Actions') }}">
                        <img class="avatar" src="{{ $author?->avatar() }}" alt="" loading="lazy">
                    </button>
                    <div>
                        @if ($author)<span class="online-dot {{ $author->isOnline() ? 'on' : '' }}" title="{{ $author->isOnline() ? __('Online') : __('Offline') }}"></span>@endif<button type="button" class="author-trigger author-name" x-on:click="open = !open" style="color: {{ $author?->displayColor() }}">{{ $author?->name ?? __('deleted') }} ▾</button>
                        <div class="dropdown-menu post-menu" x-show="open" x-cloak x-transition.origin.top.left x-on:click="open = false">
                            @if ($author)
                                <a href="{{ route('profile', $author) }}" wire:navigate>👤 {{ __('Profile') }}</a>
                                @auth @if ($author->id !== auth()->id())<a href="{{ route('messages', ['to' => $author->name]) }}" wire:navigate>✉️ {{ __('Send a message') }}</a>@endif @endauth
                            @endif
                            @auth
                                @if ($canReply && ! $post->trashed() && auth()->user()->can('viewContent', $post))<button type="button" wire:click="quote({{ $post->id }})">🗨️ {{ __('Quote') }}</button>@endif
                                @can('update', $post)<button type="button" wire:click="startEdit({{ $post->id }})">✏️ {{ __('Edit') }}</button>@endcan
                                <button type="button" wire:click="toggleBookmark({{ $post->id }})">{{ in_array($post->id, $bookmarked) ? '🔖 '.__('Remove bookmark') : '🔖 '.__('Bookmark') }}</button>
                                @can('solve', $thread)<button type="button" wire:click="markSolution({{ $post->id }})">{{ $thread->solution_post_id === $post->id ? '✖️ '.__('Unmark solution') : '✅ '.__('Mark as solution') }}</button>@endcan
                                @if ($post->edited_at)@can('viewHistory', $post)<button type="button" wire:click="showHistory({{ $post->id }})">🕘 {{ __('Edit history') }}</button>@endcan @endif
                                @can('report', $post)<button type="button" wire:click="startReport({{ $post->id }})">🚩 {{ __('Report') }}</button>@endcan
                                @if ($author && $author->id !== auth()->id() && ! $author->isStaff())
                                    @if (auth()->user()->hasBlocked($author))
                                        <button type="button" wire:click="toggleBlock({{ $author->id }})">✅ {{ __('Unblock this user') }}</button>
                                    @else
                                        <button type="button" class="danger-item" x-on:click="$confirm(@js(__(':name will no longer be able to message you, mention or quote you, react to your posts or send you friend requests. Their posts will be collapsed for you.', ['name' => $author->name])), () => $wire.toggleBlock({{ $author->id }}), { title: @js(__('Block this user?')), label: @js(__('Block')) })">⛔ {{ __('Block this user') }}</button>
                                    @endif
                                @endif
                                @can('hide', $post)
                                    <div class="dropdown-sep"></div>
                                    <div class="menu-label">{{ __('Moderation') }}</div>
                                    <button type="button" wire:click="toggleModerated({{ $post->id }})">{{ $post->moderated_at ? '⚖️ '.__('Remove moderation note') : '⚖️ '.__('Moderate…') }}</button>
                                    <button type="button" wire:click="toggleHidden({{ $post->id }})">{{ $post->is_hidden ? '👁️ '.__('Unhide') : '🙈 '.__('Hide…') }}</button>
                                    @if ($post->trashed())
                                        <button type="button" wire:click="restorePost({{ $post->id }})">♻️ {{ __('Restore') }}</button>
                                    @else
                                        <button type="button" class="danger-item" x-on:click="$confirm(@js(__('The post disappears for everyone except moderators. It can be restored later.')), () => $wire.deletePost({{ $post->id }}), { title: @js(__('Delete this post?')), label: @js(__('Delete')) })">🗑️ {{ __('Delete') }}</button>
                                    @endif
                                    @can('view-ips')
                                        @if ($author)<button type="button" x-on:click="$dispatch('open-ip-info', { userId: {{ $author->id }}, postId: {{ $post->id }} })">🌐 {{ __('IP & accounts') }}</button>@endif
                                    @endcan
                                    @if ($author)
                                        <a href="{{ route('moderation.users.show', $author) }}" wire:navigate>🗂️ {{ __('User file') }}</a>
                                    @endif
                                    @if ($author && auth()->user()->can('sanction', $author))
                                        <button type="button" x-on:click="$dispatch('open-sanction', { userId: {{ $author->id }} })">⚖️ {{ __('Sanction…') }}</button>
                                    @endif
                                @endcan
                            @else
                                <button type="button" x-on:click="$dispatch('open-modal', { name: 'auth', tab: 'login' })">🔑 {{ __('Log in to interact') }}</button>
                            @endauth
                        </div>
                        @foreach (($author?->roleAssignments() ?? collect())->where('name', '!=', 'member')->unique('name') as $role)
                            <span class="role" style="color: {{ $role->color }}">{{ $role->label }}</span>
                        @endforeach
                        @if ($thread->section->guild && $author?->guildMemberships->contains('guild_id', $thread->section->guild_id))
                            <a class="author-guild" href="{{ route('guilds.show', $thread->section->guild) }}" wire:navigate>🏰 {{ $thread->section->guild->name }}</a>
                        @endif
                        <div class="dateunderauthor" title="{{ $post->created_at->isoFormat('LLLL') }}">{{ $post->created_at->shortDateTime() }}</div>
                    </div>
                </div>
                <div class="post-main">
                    @if ($editingId === $post->id)
                        <form wire:submit="saveEdit">
                            <x-editor id="edit-body-{{ $post->id }}" model="editBody" rows="8" />
                            <div style="margin-top:6px">
                                <button class="btn primary small" type="submit">{{ __('Save') }}</button>
                                <button class="btn small" type="button" wire:click="cancelEdit">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                    @else
                        @if ($author && in_array($author->id, $ignored, true))
                            <div x-data="{ show: false }">
                                <div x-show="! show" class="ignored-note">🙈 {{ __('Post from a user you ignore or blocked.') }} <button type="button" class="linkbtn" x-on:click="show = true">{{ __('Show it') }}</button></div>
                                <div x-show="show" x-cloak>
                        @endif
                        @if ($post->id === $highlightPostId && $highlightStyle === 'targeted')<div class="target-badge">📍 {{ __('You came here for this post') }}</div>@endif
                        @if (in_array($post->id, $bookmarked))<span class="bookmark-flag" title="{{ __('Bookmarked') }}">🔖</span>@endif
                        @if ($thread->solution_post_id === $post->id)<div class="solution-badge">✅ {{ __('Solution') }}</div>@endif
                        <div class="post-body">
                            @if ($post->trashed())<div class="error">🗑️ {{ __('Deleted :when.', ['when' => $post->deleted_at->diffForHumans()]) }} {{ $post->deleter ? __('Deleted by :name.', ['name' => $post->deleter->name]) : '' }} {{ __('Only moderators can see it.') }}</div>@endif
                            @if ($post->is_hidden)<div class="error">🛡️ {{ __('Hidden by :name. Reason: :reason', ['name' => $post->hider?->name ?? __('a moderator'), 'reason' => $post->hide_reason ?? '—']) }} {{ __('Only you and the moderators can see it.') }}</div>@endif
                            @if ($post->moderated_at)
                                <div class="moderated-note">⚖️ {{ __('Moderated by :name, reason: :reason', ['name' => $post->moderator?->name ?? __('a moderator'), 'reason' => $post->moderation_reason]) }}</div>
                            @endif
                            @can('viewContent', $post)
                                <div class="{{ $post->moderated_at ? 'moderated-body' : '' }}">
                                {!! $post->body_html !!}
                                </div>
                            @endcan
                        </div>
                        @if ($author && in_array($author->id, $ignored, true))
                                </div>
                            </div>
                        @endif
                        @if ($postPoll = $polls->get($post->id))
                            @include('partials.poll', ['poll' => $postPoll, 'myVotes' => $myVotes[$postPoll->id] ?? [], 'voterCount' => $voterCounts[$postPoll->id] ?? 0, 'voterNames' => $voterNames])
                        @endif
                        @if ($author?->signature)
                            <div class="signature">{{ $author->signature }}</div>
                        @endif
                    @endif

                    <div class="post-footer">
                        <div class="reactions">
                            @php($grouped = $post->reactions->groupBy('type'))
                            @foreach ($reactionTypes as $type => $glyph)
                                @php($list = $grouped->get($type, collect()))
                                @php($mine = auth()->check() && $list->contains('user_id', auth()->id()))
                                @php($who = $list->pluck('user.name')->filter()->take(15)->implode(', ').($list->count() > 15 ? '…' : ''))
                                @can('react', $post)
                                    @if ($list->count() || $loop->index < 2)
                                        <button type="button" class="reaction {{ $mine ? 'mine' : '' }}" wire:click="react({{ $post->id }}, '{{ $type }}')" title="{{ $who ?: __(ucfirst($type)) }}">{{ $glyph }} {{ $list->count() ?: '' }}</button>
                                    @endif
                                @else
                                    @if ($list->count())<span class="reaction" title="{{ $who }}">{{ $glyph }} {{ $list->count() }}</span>@endif
                                @endcan
                            @endforeach
                            @can('react', $post)
                                <div class="dropdown" x-data="{ open: false }" x-on:click.outside="open = false">
                                    <button type="button" class="reaction" x-on:click="open = !open" title="{{ __('Add a reaction') }}">＋</button>
                                    <div class="dropdown-menu" style="left:0;right:auto;min-width:0;display:flex;gap:2px" x-show="open" x-cloak x-on:click="open = false">
                                        @foreach ($reactionTypes as $type => $glyph)
                                            <button type="button" style="font-size:16px;padding:4px 6px" wire:click="react({{ $post->id }}, '{{ $type }}')" title="{{ __(ucfirst($type)) }}">{{ $glyph }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @endcan
                        </div>
                        <div class="post-actions">
                            @if ($post->edited_at)<span title="{{ $post->edited_at->shortDateTime() }}">{{ __('edited :when', ['when' => $post->edited_at->diffForHumans()]) }}</span>@endif
                            <a href="{{ route('posts.show', $post) }}" x-data x-on:click.prevent="navigator.clipboard?.writeText($el.href); $dispatch('toast', { message: @js(__('Link copied to clipboard.')), type: 'info' })" title="{{ __('Copy link to this post') }}">#{{ $post->id }}</a>
                        </div>
                    </div>
                </div>
            </article>
        @endforeach

        <div class="panel-head" style="justify-content:flex-end">{{ $posts->links() }}</div>

        @auth
            @if ($canReply)
                <form class="form-box" wire:submit="reply">
                    <h3>{{ __('Reply') }} @if ($thread->is_locked)<span class="pill" style="color:var(--warn)">{{ __('thread locked — moderator reply') }}</span>@endif</h3>
                    <x-editor id="reply-body" model="replyBody" :preview="$preview" preview-toggle="togglePreview" draft="reply-{{ $thread->id }}" :placeholder="__('Write your reply… (Markdown supported, Ctrl+Enter to send, @name to mention)')" />
                    @include('partials.poll-builder')
                    <div style="margin-top:6px;display:flex;gap:6px;align-items:center">
                        <button class="btn primary" type="submit" wire:loading.attr="disabled" wire:target="reply">{{ __('Send') }}</button>
                        <span wire:loading wire:target="reply" class="muted" style="font-size:11px">{{ __('Sending…') }}</span>
                    </div>
                </form>
            @elseif ($thread->is_locked)
                <div class="empty">🔒 {{ __('This thread is locked.') }}</div>
            @elseif (! auth()->user()->canParticipate())
                <div class="empty">{{ auth()->user()->hasVerifiedEmail() ? __('You cannot reply right now, see the message at the top of the page.') : __('Confirm your email address to reply.') }}</div>
            @endif
        @else
            <div class="empty"><a href="{{ route('login') }}" x-data x-on:click.prevent="$dispatch('open-modal', { name: 'auth', tab: 'login' })">{{ __('Log in') }}</a> @if (\App\Support\ForumSettings::registrationOpen()){{ __('or') }} <a href="{{ route('register') }}" x-data x-on:click.prevent="$dispatch('open-modal', { name: 'auth', tab: 'register' })">{{ __('create an account') }}</a>@endif {{ __('to reply.') }}</div>
        @endauth
    </div>

    @auth
        <x-modal name="report-post" :title="__('Report this post')">
            <form wire:submit="submitReport">
                <div class="field">
                    <label for="report-reason">{{ __('Why should a moderator look at this?') }}</label>
                    <div class="chips" style="margin-bottom:6px">
                        @foreach (['Spam', 'Insults or harassment', 'Off topic', 'Double post', 'Inappropriate content'] as $reason)
                            <button type="button" class="chip" wire:click="$set('reportReason', @js(__($reason)))">{{ __($reason) }}</button>
                        @endforeach
                    </div>
                    <textarea id="report-reason" wire:model="reportReason" style="min-height:80px" maxlength="1000"></textarea>
                    @error('reportReason')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'report-post')">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn primary">{{ __('Send report') }}</button>
                </div>
            </form>
        </x-modal>
    @endauth

    @if ($canModerate)
        <x-modal name="rename-thread" :title="__('Rename thread')">
            <form wire:submit="rename">
                <div class="field">
                    <label for="rename-title">{{ __('New title') }}</label>
                    <input id="rename-title" type="text" wire:model="renameTitle" maxlength="120">
                    @error('renameTitle')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'rename-thread')">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn primary">{{ __('Rename') }}</button>
                </div>
            </form>
        </x-modal>
        <x-modal name="move-thread" :title="__('Move thread')">
            <form wire:submit="move">
                <div class="field">
                    <label for="move-target">{{ __('Destination section') }}</label>
                    <select id="move-target" wire:model="moveTarget">
                        @foreach ($moveTargets as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}{{ $s->isPrivate() ? ' ('.__('private').')' : '' }}{{ $s->id === $thread->section_id ? ' — '.__('current') : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'move-thread')">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn primary">{{ __('Move') }}</button>
                </div>
            </form>
        </x-modal>
        <x-modal name="post-reason" :title="$reasonAction === 'moderate' ? __('Moderate post') : __('Hide post')">
            <form wire:submit="confirmReason">
                <p style="margin-top:0;font-size:11px" class="muted">
                    {{ $reasonAction === 'moderate'
                        ? __('The post stays visible to everyone with a note: “Moderated by :name, reason: …”.', ['name' => auth()->user()?->name])
                        : __('The post is hidden from everyone except its author and the moderators.') }}
                    {{ trans_choice(':count post selected.|:count posts selected.', count($reasonPostIds)) }}
                </p>
                <div class="field">
                    <label for="post-reason">{{ __('Reason (required, shown to the author)') }}</label>
                    <div class="chips" style="margin-bottom:6px">
                        @foreach (['Spam', 'Insults or harassment', 'Off topic', 'Double post', 'Inappropriate content'] as $preset)
                            <button type="button" class="chip" wire:click="$set('reason', @js(__($preset)))">{{ __($preset) }}</button>
                        @endforeach
                    </div>
                    <input id="post-reason" type="text" wire:model="reason" maxlength="500" required>
                    @error('reason')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'post-reason')">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn {{ $reasonAction === 'moderate' ? 'primary' : 'danger' }}">{{ $reasonAction === 'moderate' ? __('Moderate') : __('Hide post') }}</button>
                </div>
            </form>
        </x-modal>
    @endif
    @auth
        <x-modal name="post-history" :title="__('Edit history')" width="640px">
            @forelse ($history as $revision)
                <div class="revision" wire:key="rev{{ $revision->id }}">
                    <div class="muted" style="font-size:10px;margin-bottom:4px">{{ __('Before the edit of :date by :name', ['date' => $revision->created_at->shortDateTime(), 'name' => $revision->user?->name ?? '—']) }}</div>
                    <pre class="revision-body">{{ $revision->body }}</pre>
                </div>
            @empty
                <div class="muted">{{ __('No previous version.') }}</div>
            @endforelse
        </x-modal>
    @endauth
</div>
