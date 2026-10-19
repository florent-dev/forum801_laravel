<x-layouts.app :title="$profileUser->name">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    @php($color = $profileUser->displayColor())
    <div class="scroll profile-page">
        <section class="profile-hero" style="--user-color: {{ $color }}">
            <div class="profile-banner"></div>
            <div class="profile-identity">
                <div class="profile-avatar-wrap">
                    <img class="profile-avatar" src="{{ $profileUser->avatar() }}" alt="">
                    <span class="profile-status {{ $profileUser->isOnline() ? 'on' : '' }}" title="{{ $profileUser->isOnline() ? __('Online') : __('Offline') }}"></span>
                </div>
                <div class="profile-headline">
                    <h1 style="color: {{ $color }}">{{ $profileUser->name }}</h1>
                    <div class="profile-badges">
                        <span class="pill rank-pill">⭐ {{ __($rank) }}</span>
                        @foreach ($roles->where('name', '!=', 'member') as $role)
                            <span class="pill" style="color: {{ $role->color }}">{{ $role->label }}{{ $role->pivot->scope_id ? ' · '.($scopedSections[$role->pivot->scope_id] ?? '?') : '' }}</span>
                        @endforeach
                        @if ($suspension = $profileUser->activeSanction('suspension'))
                            <span class="pill" style="color:var(--danger)">{{ $suspension->expires_at ? __('suspended until :date', ['date' => $suspension->expires_at->isoFormat('LL')]) : __('suspended') }}</span>
                        @elseif ($mute = $profileUser->activeSanction('mute'))
                            <span class="pill" style="color:var(--warn)">{{ $mute->expires_at ? __('muted until :date', ['date' => $mute->expires_at->isoFormat('LL')]) : __('muted') }}</span>
                        @endif
                    </div>
                    <div class="profile-facts">
                        <span>📅 {{ __('Joined :date', ['date' => $profileUser->created_at->translatedFormat('d M Y')]) }}</span>
                        <span>🕒 {{ $profileUser->isOnline() ? __('online now') : ($profileUser->hide_online ? __('status hidden') : ($profileUser->last_seen_at ? __('seen :when', ['when' => $profileUser->last_seen_at->diffForHumans()]) : __('never seen'))) }}</span>
                        @if ($profileUser->locale)<span title="{{ __('Language') }}"><img class="flag" src="{{ \App\Support\Locale::flagUrl($profileUser->locale) }}" alt=""> {{ \App\Support\Locale::label($profileUser->locale) }}</span>@endif
                    </div>
                </div>
                <div class="profile-actions">
                    <livewire:profile-actions :user="$profileUser" />
                </div>
            </div>
            @if ($suspension ?? null)
                @can('access-moderation')<div class="profile-alert">{{ __('Reason: :reason', ['reason' => $suspension->reason]) }}</div>@endcan
            @endif
        </section>

        <section class="profile-stats-grid">
            <div class="stat-card"><strong>{{ number_format($postCount) }}</strong><span>{{ __('posts') }}</span></div>
            <div class="stat-card"><strong>{{ number_format($threadCount) }}</strong><span>{{ __('threads') }}</span></div>
            <div class="stat-card"><strong>{{ number_format($reactionsReceived) }}</strong><span>{{ __('reactions received') }}</span></div>
            <div class="stat-card"><strong>{{ number_format($solutions) }}</strong><span>{{ __('solutions') }}</span></div>
            <div class="stat-card"><strong>{{ number_format($friendCount) }}</strong><span>{{ __('friends') }}</span></div>
        </section>

        <div class="profile-columns">
            <aside class="profile-side">
                <div class="profile-card">
                    <h3>{{ __('About') }}</h3>
                    @if ($profileUser->bio)
                        <div class="profile-bio">{{ $profileUser->bio }}</div>
                    @else
                        <div class="muted">{{ __('This user has not written anything yet.') }}</div>
                    @endif
                    @if ($profileUser->signature)
                        <div class="signature" style="margin-top:10px">{{ $profileUser->signature }}</div>
                    @endif
                </div>
                <div class="profile-card">
                    <h3>{{ __('Friends') }} <span class="muted">({{ $friendCount }})</span></h3>
                    @forelse ($friends->take(12) as $friend)
                        <a class="friend-chip" href="{{ route('profile', $friend) }}" wire:navigate title="{{ $friend->name }}">
                            <img src="{{ $friend->avatar() }}" alt=""><span style="color: {{ $friend->displayColor() }}">{{ $friend->name }}</span>
                        </a>
                    @empty
                        <div class="muted">{{ __('No friends yet.') }}</div>
                    @endforelse
                    @if ($friendCount > 12)<a class="linkbtn" href="{{ route('profile', [$profileUser, 'tab' => 'friends']) }}" wire:navigate>{{ __('See all') }}</a>@endif
                </div>
            </aside>

            <main class="profile-main">
                <div class="panel-head" style="padding:0 0 8px">
                    <div class="tabs">
                        <a href="{{ route('profile', $profileUser) }}" class="{{ $tab === 'posts' ? 'active' : '' }}" wire:navigate>💬 {{ __('Posts') }}</a>
                        <a href="{{ route('profile', [$profileUser, 'tab' => 'threads']) }}" class="{{ $tab === 'threads' ? 'active' : '' }}" wire:navigate>🧵 {{ __('Threads') }}</a>
                        <a href="{{ route('profile', [$profileUser, 'tab' => 'friends']) }}" class="{{ $tab === 'friends' ? 'active' : '' }}" wire:navigate>🤝 {{ __('Friends') }}</a>
                    </div>
                    @if ($tab !== 'friends')@include('partials.pager-links', ['paginator' => $tab === 'threads' ? $threads : $posts])@endif
                </div>
                @if ($tab === 'threads')
                    @forelse ($threads as $thread)
                        <a class="activity-card" href="{{ $thread->url() }}" wire:navigate>
                            <div class="activity-head"><span class="title">@if ($thread->prefix)<span class="prefix prefix-{{ $thread->prefix }}">{{ __(\App\Models\Thread::PREFIXES[$thread->prefix] ?? $thread->prefix) }}</span>@endif{{ $thread->title }}</span><span class="muted">{{ $thread->section->name }}</span></div>
                            <div class="meta"><span class="count">{{ trans_choice(':count post|:count posts', $thread->posts_count) }}</span><span>{{ $thread->created_at->diffForHumans() }}</span></div>
                        </a>
                    @empty
                        <div class="empty">{{ __('No threads yet.') }}</div>
                    @endforelse
                @elseif ($tab === 'friends')
                    <div class="friend-grid">
                        @forelse ($friends as $friend)
                            <a class="friend-card" href="{{ route('profile', $friend) }}" wire:navigate>
                                <img src="{{ $friend->avatar() }}" alt="">
                                <span style="color: {{ $friend->displayColor() }}"><span class="online-dot {{ $friend->isOnline() ? 'on' : '' }}"></span>{{ $friend->name }}</span>
                            </a>
                        @empty
                            <div class="empty">{{ __('No friends yet.') }}</div>
                        @endforelse
                    </div>
                @else
                    @forelse ($posts as $post)
                        <a class="activity-card" href="{{ route('posts.show', $post) }}" wire:navigate>
                            <div class="activity-head"><span class="title">{{ $post->thread->title }}</span><span class="muted">{{ $post->thread->section->name }} · {{ $post->created_at->diffForHumans() }}</span></div>
                            <div class="activity-body">{{ $post->excerpt(260) }}</div>
                        </a>
                    @empty
                        <div class="empty">{{ __('No posts yet.') }}</div>
                    @endforelse
                @endif
            </main>
        </div>
    </div>
</x-layouts.app>
