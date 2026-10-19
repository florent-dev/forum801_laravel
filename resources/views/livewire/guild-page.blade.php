<div style="display:flex;flex-direction:column;min-height:0;flex:1">
<div class="panel-head">
    <a class="btn small back-link" href="{{ route('guilds.index') }}" wire:navigate><span class="back">&lsaquo;</span> <x-art name="guilds" size="16" /> {{ __('Guilds') }}</a>
</div>
<div class="scroll profile-page">
    <section class="profile-hero">
        <div class="profile-banner guild-banner"></div>
        <div class="profile-identity">
            <div class="profile-avatar-wrap"><img class="profile-avatar" src="{{ $guild->emblemUrl() }}" alt="" style="object-fit:contain;padding:8px"></div>
            <div class="profile-headline">
                <h1>{{ $guild->name }} <span class="guild-tag">[{{ $guild->tag }}]</span></h1>
                <div class="profile-facts">
                    <span><img class="flag" src="{{ $guild->flagUrl() }}" alt=""> {{ \App\Support\Community::label($guild->community) }}</span>
                    <span>👥 {{ trans_choice(':count member|:count members', $members->count()) }}</span>
                    <span>📅 {{ __('Founded :date', ['date' => $guild->created_at->translatedFormat('d M Y')]) }}</span>
                    <span class="recruit recruit-{{ $guild->recruitment }}">{{ ['open' => __('Open to everyone'), 'application' => __('Recruiting on application'), 'closed' => __('Not recruiting')][$guild->recruitment] }}</span>
                </div>
            </div>
            <div class="profile-actions" style="display:flex;gap:6px;flex-wrap:wrap">
                @auth
                    @if ($myRole)
                        @if ($section)<a class="btn small primary" href="{{ route('sections.show', $section) }}" wire:navigate>💬 {{ __('Guild forum') }}</a>@endif
                        @can('manageMembers', $guild)<a class="btn small" href="{{ route('guilds.manage', $guild) }}" wire:navigate>⚙️ {{ __('Manage') }}</a>@endcan
                        @if ($myRole !== 'owner')
                            <button type="button" class="btn small" x-data x-on:click="$confirm(@js(__('Leave :name?', ['name' => $guild->name])), () => $wire.leave(), { label: @js(__('Leave')) })">{{ __('Leave') }}</button>
                        @endif
                    @elseif ($pending)
                        <span class="pill" style="color:var(--warn)">⏳ {{ __('Application pending') }}</span>
                    @else
                        @can('join', $guild)
                            @if ($guild->recruitment === 'open')
                                <button type="button" class="btn small primary" wire:click="join">＋ {{ __('Join the guild') }}</button>
                            @else
                                <button type="button" class="btn small primary" x-data x-on:click="$dispatch('open-modal', 'apply-guild')">✉️ {{ __('Apply') }}</button>
                            @endif
                        @endcan
                        @if ($section && $guild->forum_public)<a class="btn small" href="{{ route('sections.show', $section) }}" wire:navigate>💬 {{ __('Guild forum') }}</a>@endif
                    @endif
                    @can('admin')<a class="btn small" href="{{ route('guilds.manage', $guild) }}" wire:navigate>🔧 {{ __('Admin') }}</a>@endcan
                @endauth
            </div>
        </div>
        @error('application')<div class="profile-alert">{{ $message }}</div>@enderror
    </section>

    <div class="profile-columns">
        <aside class="profile-side">
            <div class="profile-card">
                <h3>{{ __('Members') }}</h3>
                @foreach ($members as $member)
                    <a class="guild-member" href="{{ route('profile', $member->user) }}" wire:navigate>
                        <img src="{{ $member->user->avatar() }}" alt="">
                        <span style="color: {{ $member->user->displayColor() }};flex:1">{{ $member->user->name }}</span>
                        @if ($member->role !== 'member')<span class="pill guild-role-{{ $member->role }}">{{ __(ucfirst($member->role)) }}</span>@endif
                    </a>
                @endforeach
            </div>
        </aside>
        <main class="profile-main">
            <div class="profile-card post-body">
                @if ($guild->description_html)
                    {!! $guild->description_html !!}
                @else
                    <span class="muted">{{ __('This guild has not written its presentation yet.') }}</span>
                @endif
            </div>
            @if ($threads->isNotEmpty())
                <div class="profile-card">
                    <h3>💬 {{ __('Latest in the guild forum') }}</h3>
                    @foreach ($threads as $thread)
                        <a class="activity-card" href="{{ $thread->url() }}" wire:navigate style="margin:0 0 6px">
                            <div class="activity-head"><span class="title">{{ $thread->title }}</span><span class="muted">{{ $thread->last_post_at?->diffForHumans() }}</span></div>
                        </a>
                    @endforeach
                </div>
            @endif
        </main>
    </div>

    @auth
        <x-modal name="apply-guild" :title="__('Apply to :name', ['name' => $guild->name])">
            <form wire:submit="join">
                <div class="field">
                    <label for="apply-msg">{{ __('Introduce yourself to the officers') }}</label>
                    <textarea id="apply-msg" wire:model="applicationMessage" rows="5" maxlength="1000"></textarea>
                    @error('applicationMessage')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'apply-guild')">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn primary">{{ __('Send my application') }}</button>
                </div>
            </form>
        </x-modal>
    @endauth
</div>
</div>
