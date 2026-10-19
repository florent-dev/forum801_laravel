<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="alternate" type="application/rss+xml" title="{{ config('app.name') }}" href="{{ route('feeds.latest') }}">
    <meta name="mention-endpoint" content="{{ auth()->check() ? route('api.users') : '' }}">
    <link rel="icon" href="{{ asset('img/sections/Discussions.png') }}">
    <link rel="stylesheet" href="{{ asset('css/forum.css') }}?v={{ filemtime(public_path('css/forum.css')) }}">
    @livewireStyles
</head>
<body class="{{ $bodyClass ?? '' }}" @if (! empty($drawerOpen)) data-drawer-default="open" @endif>
    <header class="topbar">
        <button type="button" class="drawer-toggle" aria-label="{{ __('Menu') }}" aria-controls="left-panel" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <div class="logo">
            <a href="{{ route('home') }}" wire:navigate><img src="{{ asset('img/logo.png') }}" alt="{{ config('app.name') }}"></a>
            {{-- Refreshed every minute: the header is on every page --}}
            @php($onlineCount = cache()->remember('header-online-count', 60, fn () => \App\Models\User::visiblyOnline()->count()))
            <a class="online-count" href="{{ route('online') }}" wire:navigate><span class="online-dot on"></span>{{ __(':count online', ['count' => $onlineCount]) }}</a>
        </div>
        <div class="search">
            <button type="button" class="search-trigger" x-data x-on:click="$dispatch('open-search')" title="{{ __('Search') }} (/)">
                <x-art name="search" size="18" />
                <span class="search-placeholder">{{ request()->routeIs('search') && request('q') ? request('q') : __('Search the forum…') }}</span>
                <kbd>/</kbd>
            </button>
        </div>
        <div class="usermenu">
            @php($currentCommunity = \App\Support\Community::current())
            <button type="button" class="hb-item tour-trigger" x-data x-on:click="$dispatch('start-tour')" title="{{ __('Guided tour') }}"><x-art name="help" class="hb-icon" /></button>
            @php($currentLocale = \App\Support\Locale::current())
            <div class="dropdown language-switch" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                <button type="button" class="hb-item" x-on:click="open = !open" title="{{ __('Language: :name', ['name' => \App\Support\Locale::label($currentLocale)]) }}" aria-haspopup="true">
                    <x-art name="language" class="hb-icon" /> <span class="hb-label">{{ strtoupper(substr($currentLocale, 0, 2)) }}</span> ▾
                </button>
                <form class="dropdown-menu community-menu" x-show="open" x-cloak x-transition.origin.top.right method="POST" action="{{ route('language.update') }}">
                    @csrf
                    <div class="dropdown-head"><span>{{ __('Interface language') }}</span></div>
                    @foreach (\App\Support\Locale::all() as $code => $l)
                        <button type="submit" name="locale" value="{{ $code }}" class="{{ $code === $currentLocale ? 'active' : '' }}">
                            <img class="flag" src="{{ \App\Support\Locale::flagUrl($code) }}" alt=""> {{ $l['label'] }}
                        </button>
                    @endforeach
                </form>
            </div>
            <div class="dropdown community-switch" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                <button type="button" class="hb-item" x-on:click="open = !open" title="{{ __('Community: :name', ['name' => \App\Support\Community::label($currentCommunity)]) }}" aria-haspopup="true">
                    <img class="flag" src="{{ \App\Support\Community::flagUrl($currentCommunity) }}" alt="{{ $currentCommunity }}"> <span class="hb-label">{{ strtoupper($currentCommunity) }}</span> ▾
                </button>
                <form class="dropdown-menu community-menu" x-show="open" x-cloak x-transition.origin.top.right method="POST" action="{{ route('community.update') }}">
                    @csrf
                    <div class="dropdown-head"><span>{{ __('Community') }}</span></div>
                    <div class="menu-hint">{{ __('Sections shown on the home page. Your interface language does not change.') }}</div>
                    @foreach (\App\Support\Community::all() as $code => $c)
                        <button type="submit" name="community" value="{{ $code }}" class="{{ $code === $currentCommunity ? 'active' : '' }}">
                            <img class="flag" src="{{ \App\Support\Community::flagUrl($code) }}" alt=""> {{ $c['label'] }}
                        </button>
                    @endforeach
                </form>
            </div>
            @auth
                <livewire:header-bar />
            @else
                <span class="guest-actions">
                <a class="btn" href="{{ route('login') }}" x-data x-on:click.prevent="$dispatch('open-modal', { name: 'auth', tab: 'login' })">{{ __('Log in') }}</a>
                @if (\App\Support\ForumSettings::registrationOpen())<a class="btn primary" href="{{ route('register') }}" x-data x-on:click.prevent="$dispatch('open-modal', { name: 'auth', tab: 'register' })">{{ __('Register') }}</a>@endif
                </span>
            @endauth
        </div>
    </header>

    @include('partials.account-banner')

    <main class="frame">
        <aside class="panel panel-left" id="left-panel">
            {{-- Shown on small screens, where this column becomes a drawer --}}
            <div class="drawer-bar">
                <a href="{{ route('home') }}" wire:navigate class="drawer-home"><img src="{{ asset('img/logo.png') }}" alt="{{ config('app.name') }}"></a>
                <button type="button" class="drawer-close" aria-label="{{ __('Close') }}">&times;</button>
            </div>
            {{ $left ?? '' }}
        </aside>
        <div class="drawer-backdrop" aria-hidden="true"></div>
        <section class="panel panel-right">
            {{ $slot }}
        </section>
    </main>

    @guest
        <livewire:auth-modal />
    @endguest
    @can('access-moderation')
        <livewire:sanction-modal />
    @endcan
    @can('view-ips')
        <livewire:ip-info-modal />
    @endcan
    @include('partials.search-modal')
    @include('partials.tour')

    {{-- Shared confirm dialog driven by the $confirm Alpine helper --}}
    <div x-data x-show="$store.confirm.show" x-cloak class="modal-backdrop" x-transition.opacity.duration.150ms x-on:keydown.escape.window="$store.confirm.show = false">
        <div class="modal" style="max-width:400px" x-on:click.outside="$store.confirm.show = false" role="alertdialog" aria-modal="true">
            <div class="modal-head"><h3 x-text="$store.confirm.title"></h3><button type="button" class="modal-close" x-on:click="$store.confirm.show = false" aria-label="{{ __('Close') }}">&times;</button></div>
            <div class="modal-body">
                <p x-text="$store.confirm.message" style="margin-top:0"></p>
                <div class="modal-actions">
                    <button type="button" class="btn" x-on:click="$store.confirm.show = false">{{ __('Cancel') }}</button>
                    <button type="button" class="btn" :class="$store.confirm.danger ? 'danger' : 'primary'" x-text="$store.confirm.label" x-on:click="$store.confirm.accept()" x-ref="ok"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- Image lightbox for pictures inside posts --}}
    <div x-data x-show="$store.lightbox.src" x-cloak class="modal-backdrop lightbox" x-on:click="$store.lightbox.src = null" x-on:keydown.escape.window="$store.lightbox.src = null" x-transition.opacity>
        <img :src="$store.lightbox.src" alt="">
    </div>

    {{-- Toast notifications --}}
    <div class="toasts" x-data aria-live="polite">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div class="toast" :class="toast.type" x-transition x-text="toast.message" x-on:click="$store.toasts.remove(toast.id)"></div>
        </template>
    </div>

    <script data-navigate-once>
        document.addEventListener('alpine:init', () => {
            Alpine.store('confirm', {
                show: false, title: '', message: '', label: @js(__('Confirm')), danger: true, callback: null,
                ask(message, callback, options = {}) {
                    Object.assign(this, { title: options.title || @js(__('Are you sure?')), label: options.label || @js(__('Confirm')), danger: options.danger ?? true, message, callback, show: true });
                },
                accept() { this.show = false; if (this.callback) this.callback(); },
            });
            Alpine.magic('confirm', () => (message, callback, options) => Alpine.store('confirm').ask(message, callback, options));
            Alpine.store('lightbox', { src: null });
            // Guided tour: highlights each step's element and places a card next to it
            Alpine.data('forumTour', (all, done, doneUrl, key) => ({
                all, steps: [], index: 0, open: false, spot: 'display:none', cardStyle: '',
                get step() { return this.steps[this.index] || {}; },
                init() {
                    let seen = done;
                    if (seen === null) {
                        try { seen = localStorage.getItem('tourDone:' + key) === '1'; } catch (e) { seen = true; }
                    }
                    // The tour points at desktop layout elements: never start it by itself on small screens
                    if (! seen && ! window.matchMedia('(max-width: 800px)').matches) setTimeout(() => this.start(), 900);
                    window.addEventListener('resize', () => this.open && this.place());
                },
                start() {
                    const shown = el => el && el.getClientRects().length > 0;
                    this.steps = this.all.filter(s => ! s.selector || shown(document.querySelector(s.selector)));
                    this.index = 0;
                    this.open = true;
                    this.$nextTick(() => this.place());
                },
                next() { this.index < this.steps.length - 1 ? (this.index++, this.place()) : this.finish(); },
                prev() { if (this.index > 0) { this.index--; this.place(); } },
                key(e) {
                    if (! this.open) return;
                    if (e.key === 'Escape') this.finish();
                    if (e.key === 'ArrowRight') this.next();
                    if (e.key === 'ArrowLeft') this.prev();
                },
                place() {
                    const el = this.step.selector && document.querySelector(this.step.selector);
                    const cardW = Math.min(340, window.innerWidth - 32), cardH = 230, gap = 14, pad = 6;
                    if (! el) {
                        this.spot = 'display:none';
                        this.cardStyle = `left:${(window.innerWidth - cardW) / 2}px;top:${Math.max(16, window.innerHeight / 2 - cardH / 2)}px;width:${cardW}px`;
                        return;
                    }
                    const r = el.getBoundingClientRect();
                    this.spot = `left:${r.left - pad}px;top:${r.top - pad}px;width:${r.width + pad * 2}px;height:${r.height + pad * 2}px`;
                    let left, top;
                    if (r.height > window.innerHeight * 0.5) {
                        // Tall element: put the card beside it
                        left = r.right + gap + cardW < window.innerWidth ? r.right + gap : r.left - gap - cardW;
                        top = Math.max(16, r.top + 40);
                    } else {
                        left = r.left;
                        top = r.bottom + gap + cardH < window.innerHeight ? r.bottom + gap : r.top - gap - cardH;
                    }
                    left = Math.min(Math.max(16, left), window.innerWidth - cardW - 16);
                    top = Math.min(Math.max(16, top), window.innerHeight - cardH - 16);
                    this.cardStyle = `left:${left}px;top:${top}px;width:${cardW}px`;
                },
                finish() {
                    this.open = false;
                    if (done === null) {
                        try { localStorage.setItem('tourDone:' + key, '1'); } catch (e) {}
                    } else if (! done) {
                        fetch(doneUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                    }
                    done = done === null ? null : true;
                },
            }));
            // Unsent text is kept in this browser so a reload does not lose it
            Alpine.data('draft', (key) => ({
                key,
                init() {
                    let saved = null;
                    try { saved = localStorage.getItem('draft:' + key); } catch (e) {}
                    if (saved && ! this.$el.value) {
                        this.$el.value = saved;
                        this.$el.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                },
                save() {
                    try { this.$el.value ? localStorage.setItem('draft:' + key, this.$el.value) : localStorage.removeItem('draft:' + key); } catch (e) {}
                },
                clear() { window.forgetDraft(key); },
            }));
            Alpine.store('toasts', {
                items: [], next: 1,
                add(message, type = 'success') {
                    const id = this.next++;
                    this.items.push({ id, message, type });
                    setTimeout(() => this.remove(id), 4000);
                },
                remove(id) { this.items = this.items.filter(t => t.id !== id); },
            });
        });
        // Scroll only the panel that holds the element; scrollIntoView would also move the fixed page body
        window.scrollInPanel = function (el, block = 'start', smooth = false) {
            if (!el) return;
            const panel = el.closest('.scroll');
            // On small screens the page itself scrolls, panels do not
            if (!panel || window.innerWidth <= 800) { el.scrollIntoView({ block, behavior: smooth ? 'smooth' : 'auto' }); return; }
            const offset = el.getBoundingClientRect().top - panel.getBoundingClientRect().top + panel.scrollTop;
            const top = block === 'center' ? offset - (panel.clientHeight - el.offsetHeight) / 2 : offset - 8;
            panel.scrollTo({ top: Math.max(0, top), behavior: smooth ? 'smooth' : 'auto' });
            document.scrollingElement.scrollTop = 0;
        };
        // Safety net: on desktop the page must stay at the top, only panels scroll
        const pinPage = () => { if (window.innerWidth > 800 && document.scrollingElement.scrollTop !== 0) document.scrollingElement.scrollTop = 0; };
        window.addEventListener('scroll', pinPage, { passive: true });
        document.addEventListener('livewire:navigated', () => setTimeout(pinPage, 50));
        window.forgetDraft = function (key) { try { localStorage.removeItem('draft:' + key); } catch (e) {} };
        window.addEventListener('toast', e => Alpine.store('toasts').add(e.detail.message, e.detail.type || 'success'));

        // Basic markdown toolbar for any textarea with data-toolbar
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-md]');
            if (!btn) return;
            e.preventDefault();
            const ta = document.getElementById(btn.closest('[data-toolbar]').dataset.toolbar);
            const [before, after, placeholder] = JSON.parse(btn.dataset.md);
            const start = ta.selectionStart, end = ta.selectionEnd;
            const selected = ta.value.substring(start, end) || placeholder;
            ta.setRangeText(before + selected + after, start, end, 'select');
            ta.focus();
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        });
        // Editor shortcuts: Ctrl+B / Ctrl+I / Ctrl+K and list continuation on Enter
        document.addEventListener('keydown', function (e) {
            const ta = e.target;
            if (!ta.matches || !ta.matches('textarea[data-editor]')) return;
            const wrap = (before, after, placeholder) => {
                const start = ta.selectionStart, end = ta.selectionEnd;
                const selected = ta.value.substring(start, end) || placeholder;
                ta.setRangeText(before + selected + after, start, end, 'select');
                ta.setSelectionRange(start + before.length, start + before.length + selected.length);
                ta.dispatchEvent(new Event('input', { bubbles: true }));
            };
            if ((e.ctrlKey || e.metaKey) && ! e.shiftKey && ! e.altKey) {
                const key = e.key.toLowerCase();
                if (key === 'b') { e.preventDefault(); return wrap('**', '**', 'bold'); }
                if (key === 'i') { e.preventDefault(); return wrap('*', '*', 'italic'); }
                if (key === 'k') { e.preventDefault(); return wrap('[', '](https://)', 'link'); }
            }
            if (e.key === 'Enter' && ! e.shiftKey && ! e.ctrlKey && ! e.metaKey && ta.selectionStart === ta.selectionEnd) {
                const pos = ta.selectionStart;
                const lineStart = ta.value.lastIndexOf('\n', pos - 1) + 1;
                const line = ta.value.substring(lineStart, pos);
                const match = line.match(/^(\s*)([-*]|(\d+)\.)\s(.*)$/);
                if (! match) return;
                e.preventDefault();
                if (match[4] === '') {
                    // An empty item ends the list
                    ta.setRangeText('', lineStart, pos, 'end');
                } else {
                    const marker = match[3] ? (parseInt(match[3], 10) + 1) + '.' : match[2];
                    ta.setRangeText('\n' + match[1] + marker + ' ', pos, pos, 'end');
                }
                ta.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        // Ctrl+Enter submits the form of the focused textarea
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && e.target.matches('textarea')) {
                e.preventDefault();
                e.target.form?.requestSubmit();
            }
        });
        // Small screens: the left column is a drawer
        const isMobile = () => window.innerWidth <= 800;
        window.drawer = {
            open() {
                if (!isMobile()) return;
                document.body.classList.add('drawer-open');
                document.querySelector('.drawer-toggle')?.setAttribute('aria-expanded', 'true');
            },
            close() {
                document.body.classList.remove('drawer-open');
                document.querySelector('.drawer-toggle')?.setAttribute('aria-expanded', 'false');
            },
            toggle() { document.body.classList.contains('drawer-open') ? this.close() : this.open(); },
        };
        document.addEventListener('click', function (e) {
            if (e.target.closest('.drawer-toggle')) { window.drawer.toggle(); return; }
            if (e.target.closest('.drawer-close, .drawer-backdrop')) { window.drawer.close(); }
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') window.drawer.close(); });
        // Swipe left inside the drawer to close it
        let touchX = null;
        document.addEventListener('touchstart', e => { touchX = e.target.closest('#left-panel') ? e.touches[0].clientX : null; }, { passive: true });
        document.addEventListener('touchend', e => {
            if (touchX !== null && e.changedTouches[0].clientX - touchX < -60) window.drawer.close();
            touchX = null;
        }, { passive: true });
        // Picking something in the drawer closes it; pages without main content open it
        document.addEventListener('livewire:navigating', () => window.drawer.close());
        const drawerDefault = () => { if (document.body.dataset.drawerDefault === 'open') window.drawer.open(); };
        document.addEventListener('livewire:navigated', drawerDefault);
        // Spoilers reveal on click and hide again on a second click
        document.addEventListener('click', function (e) {
            const spoiler = e.target.closest('.post-body .spoiler, .md-preview .spoiler');
            if (!spoiler) return;
            e.preventDefault();
            e.stopPropagation();
            spoiler.classList.toggle('revealed');
        }, true);
        // Open post images in the lightbox
        document.addEventListener('click', function (e) {
            const img = e.target.closest('.post-body img');
            if (!img || img.closest('a')) return;
            Alpine.store('lightbox').src = img.src;
        });
        // Quote button fills the reply box
        window.addEventListener('quote-post', function (e) {
            const ta = document.getElementById('reply-body');
            if (!ta) return;
            const quoted = e.detail.body.split('\n').map(l => '> ' + l).join('\n');
            ta.value = (ta.value ? ta.value + '\n\n' : '') + '> **' + e.detail.author + '** wrote:\n' + quoted + '\n\n';
            ta.dispatchEvent(new Event('input', { bubbles: true }));
            window.scrollInPanel(ta, 'center', true);
            ta.focus();
        });
        // Bring a freshly shown post into view once Livewire has re-rendered
        window.addEventListener('scroll-to-post', function (e) {
            setTimeout(function () {
                const el = document.getElementById('post-' + e.detail.id);
                if (el) window.scrollInPanel(el, 'start', true);
            }, 80);
        });
        // Highlight the post targeted by the URL hash
        function highlightHash() {
            const el = location.hash && document.querySelector(location.hash);
            if (el && el.classList.contains('thread-post')) {
                window.scrollInPanel(el, 'start');
                el.classList.add('flash-target');
                setTimeout(() => el.classList.remove('flash-target'), 2000);
            }
        }
        document.addEventListener('livewire:navigated', highlightHash);
        window.addEventListener('load', highlightHash);
    </script>
    @if (session('toast'))
        <script>(function () { const add = () => Alpine.store('toasts').add(@js(session('toast'))); window.Alpine && Alpine.store('toasts') ? add() : document.addEventListener('alpine:initialized', add); })();</script>
    @endif
    @livewireScripts
    <script src="{{ asset('js/forum-nav.js') }}?v={{ filemtime(public_path('js/forum-nav.js')) }}" data-navigate-once></script>
</body>
</html>
