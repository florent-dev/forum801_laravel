@php($sections = [
    'profile' => ['profile', __('Profile'), __('Avatar, about me, signature')],
    'language' => ['language', __('Language'), __('Interface language')],
    'privacy' => ['privacy', __('Privacy'), __('Online status, mentions, ignored users')],
    'account' => ['account', __('Account & security'), __('Email address and password')],
])
<x-layouts.app :title="__('Settings')">
    <x-slot:left>
        <div class="panel-head"><h2>⚙️ {{ __('Settings') }}</h2></div>
        <div class="scroll">
            <div class="settings-me">
                <img src="{{ $user->avatar() }}" alt="">
                <div>
                    <div style="color: {{ $user->displayColor() }}">{{ $user->name }}</div>
                    <a href="{{ route('profile', $user) }}" wire:navigate class="muted" style="font-size:10px">{{ __('View my profile') }} →</a>
                </div>
            </div>
            @foreach ($sections as $key => [$icon, $label, $hint])
                <a class="ui-content settings-nav {{ $tab === $key ? 'selected' : '' }}" href="{{ route('settings', $key) }}" wire:navigate>
                    <x-art :name="$icon" size="26" class="settings-icon" />
                    <span><span class="title">{{ $label }}</span><span class="meta">{{ $hint }}</span></span>
                </a>
            @endforeach
        </div>
    </x-slot:left>

    <div class="panel-head"><h1 class="with-art"><x-art :name="$sections[$tab][0]" /> {{ $sections[$tab][1] }}</h1></div>
    <div class="scroll settings-page">
        @if ($tab === 'profile')
            <form class="settings-card" method="POST" action="{{ route('settings.update') }}"
                  x-data="{ avatar: @js(old('avatar_url', $user->avatar_url)), fallback: @js($user->avatar_url ? '' : $user->avatar()), bio: @js(old('bio', $user->bio ?? '')), signature: @js(old('signature', $user->signature ?? '')) }">
                @csrf @method('PUT')
                <div class="settings-row">
                    <div class="settings-label">
                        <strong>{{ __('Avatar') }}</strong>
                        <span>{{ __('Paste the https address of an image. Leave empty to use your initial.') }}</span>
                    </div>
                    <div class="settings-field avatar-field">
                        <img class="avatar-preview" :src="avatar || fallback || @js($user->avatar())" alt="" x-on:error="$el.src = @js('data:image/svg+xml;base64,'.base64_encode('<svg xmlns=\'http://www.w3.org/2000/svg\'/>'))">
                        <div style="flex:1">
                            <input type="text" name="avatar_url" x-model="avatar" placeholder="https://…" autocomplete="off">
                            @error('avatar_url')<div class="error">{{ $message }}</div>@enderror
                            <button type="button" class="linkbtn" x-show="avatar" x-on:click="avatar = ''">{{ __('Use my initial instead') }}</button>
                        </div>
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-label">
                        <strong>{{ __('About me') }}</strong>
                        <span>{{ __('Shown on your profile.') }}</span>
                    </div>
                    <div class="settings-field">
                        <textarea name="bio" x-model="bio" maxlength="2000" style="min-height:110px"></textarea>
                        <div class="counter" :class="bio.length > 1900 && 'warn'"><span x-text="bio.length"></span> / 2000</div>
                        @error('bio')<div class="error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-label">
                        <strong>{{ __('Signature') }}</strong>
                        <span>{{ __('Shown under each of your posts.') }}</span>
                    </div>
                    <div class="settings-field">
                        <input type="text" name="signature" x-model="signature" maxlength="300">
                        <div class="counter"><span x-text="signature.length"></span> / 300</div>
                        <div class="signature" x-show="signature" x-text="signature"></div>
                        @error('signature')<div class="error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="settings-actions"><button class="btn primary" type="submit">{{ __('Save profile') }}</button></div>
            </form>

        @elseif ($tab === 'language')
            <form class="settings-card" method="POST" action="{{ route('language.update') }}">
                @csrf
                <p class="settings-intro">
                    {{ __('Choose the language of the interface. It is saved on your account.') }}<br>
                    {{ __('The community you browse is chosen with the flag menu at the top. It never changes your language.') }}
                </p>
                <div class="community-grid">
                    @foreach (\App\Support\Locale::all() as $code => $l)
                        <label class="community-card {{ $user->locale === $code ? 'active' : '' }}">
                            <input type="radio" name="locale" value="{{ $code }}" @checked($user->locale === $code) onchange="this.form.requestSubmit()">
                            <img src="{{ \App\Support\Locale::flagUrl($code) }}" alt="">
                            <span>{{ $l['label'] }}</span>
                            @if ($user->locale === $code)<span class="check">✓</span>@endif
                        </label>
                    @endforeach
                </div>
            </form>

        @elseif ($tab === 'privacy')
            <form class="settings-card" method="POST" action="{{ route('settings.privacy') }}">
                @csrf @method('PUT')
                <div class="settings-row">
                    <div class="settings-label">
                        <strong>{{ __('Online status') }}</strong>
                        <span>{{ __('When hidden, nobody sees that you are connected, not even your friends.') }}</span>
                    </div>
                    <div class="settings-field">
                        <label class="switch">
                            <input type="checkbox" name="hide_online" value="1" @checked($user->hide_online)>
                            <span class="slider"></span>
                            <span class="switch-label">{{ __('Hide my online status') }}</span>
                        </label>
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-label">
                        <strong>{{ __('@mentions') }}</strong>
                        <span>{{ __('Who can notify you by writing @:name in a post.', ['name' => $user->name]) }}</span>
                    </div>
                    <div class="settings-field option-cards">
                        @foreach (['everyone' => ['🌐', __('Everyone')], 'friends' => ['🤝', __('My friends only')], 'nobody' => ['🔕', __('Nobody')]] as $value => [$icon, $label])
                            <label class="option-card">
                                <input type="radio" name="mention_policy" value="{{ $value }}" @checked($user->mention_policy === $value)>
                                <span>{{ $icon }}</span>{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-label">
                        <strong>{{ __('Ignored & blocked users') }}</strong>
                        <span>{{ __('Ignoring collapses their posts. Blocking also stops them from contacting you in any way.') }}</span>
                    </div>
                    <div class="settings-field">
                        <a class="btn" href="{{ route('friends', ['tab' => 'ignored']) }}" wire:navigate>🙈 {{ trans_choice('Manage :count ignored user|Manage :count ignored users', count($user->ignoredIds())) }}</a>
                        <a class="btn" href="{{ route('friends', ['tab' => 'blocked']) }}" wire:navigate>⛔ {{ trans_choice('Manage :count blocked user|Manage :count blocked users', count($user->blockedIds())) }}</a>
                    </div>
                </div>
                <div class="settings-actions"><button class="btn primary" type="submit">{{ __('Save privacy settings') }}</button></div>
            </form>

        @else
            <form class="settings-card" method="POST" action="{{ route('settings.email') }}">
                @csrf @method('PUT')
                <h3>✉️ {{ __('Email address') }}</h3>
                <div class="settings-row">
                    <div class="settings-label">
                        <strong>{{ __('Current address') }}</strong>
                        <span>
                            @if ($user->hasVerifiedEmail())<span style="color:var(--accent-2)">✓ {{ __('confirmed') }}</span>@else<span style="color:var(--warn)">⚠ {{ __('not confirmed yet') }}</span>@endif
                        </span>
                    </div>
                    <div class="settings-field">
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                        @error('email', 'email')<div class="error">{{ $message }}</div>@enderror
                        <p class="hint">{{ __('Changing it sends a new confirmation link; you cannot post until you click it.') }}</p>
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Current password') }}</strong></div>
                    <div class="settings-field">
                        <input type="password" name="current_password" required autocomplete="current-password">
                        @error('current_password', 'email')<div class="error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="settings-actions"><button class="btn primary" type="submit">{{ __('Update email') }}</button></div>
            </form>

            <form class="settings-card" method="POST" action="{{ route('settings.password') }}" x-data="{ show: false, pw: '' }">
                @csrf @method('PUT')
                <h3>🔑 {{ __('Password') }}</h3>
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Current password') }}</strong></div>
                    <div class="settings-field">
                        <input :type="show ? 'text' : 'password'" name="current_password" required autocomplete="current-password">
                        @error('current_password', 'password')<div class="error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-label">
                        <strong>{{ __('New password') }}</strong>
                        <span>{{ __('At least 8 characters. Longer is stronger.') }}</span>
                    </div>
                    <div class="settings-field">
                        <input :type="show ? 'text' : 'password'" name="password" x-model="pw" required autocomplete="new-password">
                        <div class="strength"><span :style="'width:' + Math.min(100, pw.length * 7) + '%'" :class="pw.length < 8 ? 'weak' : (pw.length < 12 ? 'ok' : 'strong')"></span></div>
                        @error('password', 'password')<div class="error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Confirm new password') }}</strong></div>
                    <div class="settings-field">
                        <input :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password">
                        <label class="hint"><input type="checkbox" x-model="show"> {{ __('Show passwords') }}</label>
                    </div>
                </div>
                <div class="settings-actions"><button class="btn primary" type="submit">{{ __('Change password') }}</button></div>
            </form>

            <div class="settings-card">
                <h3>📦 {{ __('Your data') }}</h3>
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Download your data') }}</strong><span>{{ __('A JSON file with your account, posts, messages, votes, friends, guilds and the IP addresses we keep.') }}</span></div>
                    <div class="settings-field"><a class="btn" href="{{ route('settings.export') }}">⬇️ {{ __('Download') }}</a></div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
