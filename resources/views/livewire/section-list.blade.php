<div class="scroll" wire:poll.20s>
    @if ($sections->isEmpty())
        <div class="empty">
            <img class="flag" src="{{ \App\Support\Community::flagUrl($community) }}" alt=""> {{ __('The :community community has no sections yet.', ['community' => \App\Support\Community::label($community)]) }}<br><br>
            {{ __('Pick another community with the flag at the top of the page.') }}
        </div>
    @endif
    @foreach ($sections as $section)
        <a class="ui-content section-item" href="{{ route('sections.show', $section) }}" wire:navigate wire:key="s{{ $section->id }}">
            <img class="icon" src="{{ $section->iconUrl() }}" alt="">
            <div style="flex:1;min-width:0">
                <div class="row">
                    <span class="title">{{ $section->name }}</span>
                    <span class="section-badges">
                        @if ($section->isPrivate())<img src="{{ asset('img/locked.png') }}" class="lock-icon" alt="{{ __('private') }}" title="{{ __('Private') }}">@endif
                        <img class="flag" src="{{ $section->flagUrl() }}" alt="{{ $section->community }}" title="{{ $section->communityLabel() }}">
                    </span>
                </div>
                <div class="desc">{{ $section->description }}</div>
                <div class="meta">
                    <span>{!! trans_choice(':count thread|:count threads', $section->threads_count, ['count' => '<span class="count">'.$section->threads_count.'</span>']) !!}</span>
                    <span>{!! trans_choice(':count post|:count posts', (int) $section->posts_total, ['count' => '<span class="count">'.(int) $section->posts_total.'</span>']) !!}</span>
                    @if ($last = $section->latestThread)
                        <span title="{{ $last->title }}">· {{ $last->last_post_at?->diffForHumans(short: true) }}</span>
                        @if ($last->lastPoster)<span style="color: {{ $last->lastPoster->displayColor() }}">{{ $last->lastPoster->name }}</span>@endif
                    @endif
                </div>
            </div>
        </a>
    @endforeach

    @if ($guilds->isNotEmpty())
        <div class="list-separator"><x-art name="guilds" size="16" /> {{ trans_choice('My guild|My guilds', $guilds->count()) }}</div>
        @foreach ($guilds as $guild)
            @continue(! $guild->section)
            @php($last = $guild->section->latestThread)
            <a class="ui-content guild-entry" href="{{ route('sections.show', $guild->section) }}" wire:navigate wire:key="gs{{ $guild->id }}">
                <span class="guild-emblem"><img src="{{ $guild->emblemUrl() }}" alt=""></span>
                <div class="guild-entry-body">
                    <div class="row">
                        <span class="title">{{ $guild->name }} <span class="guild-tag">[{{ $guild->tag }}]</span></span>
                        <span class="guild-entry-badges">
                            @if ($guild->my_role !== 'member')<span class="pill guild-role-{{ $guild->my_role }}">{{ __(ucfirst($guild->my_role)) }}</span>@endif
                            @if (in_array($guild->my_role, ['owner', 'officer'], true) && $guild->pending_count)<span class="badge" title="{{ __('Applications waiting') }}">{{ $guild->pending_count }}</span>@endif
                            <img class="flag" src="{{ $guild->flagUrl() }}" alt="" title="{{ \App\Support\Community::label($guild->community) }}">
                        </span>
                    </div>
                    <div class="desc">{{ trans_choice(':count member|:count members', $guild->members_count) }}{{ $guild->forum_public ? ' · '.__('public forum') : ' · '.__('members-only forum') }}</div>
                    <div class="meta">
                        <span><span class="count">{{ $guild->section->threads_count }}</span> {{ __('threads') }}</span>
                        <span><span class="count">{{ (int) $guild->section->posts_total }}</span> {{ __('posts') }}</span>
                        @if ($last)
                            <span title="{{ $last->title }}">· {{ $last->last_post_at?->diffForHumans(short: true) }}</span>
                            @if ($last->lastPoster)<span style="color: {{ $last->lastPoster->displayColor() }}">{{ $last->lastPoster->name }}</span>@endif
                        @endif
                    </div>
                </div>
            </a>
        @endforeach
    @endif
    <a class="ui-content guilds-link" href="{{ route('guilds.index') }}" wire:navigate>
        <x-art name="guilds" /><span>{{ $guilds->isNotEmpty() ? __('Browse other guilds') : __('Guilds: find one or create yours') }}</span><span class="muted">→</span>
    </a>
    <div class="stats-box">
        <div>{!! trans_choice(':count member|:count members', $stats['members'], ['count' => '<strong>'.number_format($stats['members']).'</strong>']) !!} · {!! trans_choice(':count thread|:count threads', $stats['threads'], ['count' => '<strong>'.number_format($stats['threads']).'</strong>']) !!} · {!! trans_choice(':count post|:count posts', $stats['posts'], ['count' => '<strong>'.number_format($stats['posts']).'</strong>']) !!}</div>
        @if ($stats['newest'])<div style="margin-top:3px">{{ __('Newest member:') }} <a href="{{ route('profile', $stats['newest']->name) }}" wire:navigate>{{ $stats['newest']->name }}</a></div>@endif
        <div style="margin-top:6px"><a href="{{ route('online') }}" wire:navigate><span class="online-dot on"></span>{{ __(':count online', ['count' => $online->count()]) }}</a>:
            <span class="online-list">
                @forelse ($online as $u)
                    <a href="{{ route('profile', $u) }}" wire:navigate style="color: {{ $u->displayColor() }}">{{ $u->name }}</a>
                @empty
                    <span>{{ __('nobody right now') }}</span>
                @endforelse
            </span>
        </div>
    </div>
</div>
