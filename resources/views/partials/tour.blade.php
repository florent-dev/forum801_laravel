{{-- Guided tours, one per kind of page; steps whose element is missing are skipped --}}
@php
    $tourKey = request()->routeIs('sections.show') ? 'section' : (request()->routeIs('threads.show') ? 'thread' : 'main');
    $tours = [
        'main' => [
            ['selector' => null, 'title' => __('Welcome to :name! 🧀', ['name' => config('app.name')]), 'text' => __('This short tour shows you how the forum works. It takes one minute, and you can replay it any time with the ❓ button.')],
            ['selector' => '#left-panel', 'title' => __('Sections'), 'text' => __('The left bar lists the sections of your community. Open one to see its threads; inside a section, click its name at the top to come back here.')],
            ['selector' => '.language-switch', 'title' => __('Language'), 'text' => __('Pick the language of the interface. Members keep it in their settings.')],
            ['selector' => '.community-switch', 'title' => __('Community'), 'text' => __('Each community has its own sections. Switch here whenever you like: every community stays open to you, and your language stays the same.')],
            ['selector' => '.search', 'title' => __('Search'), 'text' => __('Search thread titles or post contents. On the results page you can also filter by section, author or community.')],
            ['selector' => '.header-bar', 'title' => __('Your corner'), 'text' => __('Unread threads, private messages and notifications (replies, mentions, quotes, friends) are counted here and refresh every few seconds.')],
            ['selector' => '.hb-user', 'title' => __('Your menu'), 'text' => __('Your profile, settings, friends, bookmarks and followed threads are one click away.')],
            ['selector' => '.guest-actions', 'title' => __('Join in'), 'text' => __('Create a free account to post, react, follow threads and chat with other users.')],
            ['selector' => null, 'title' => __('Inside a thread'), 'text' => __('Click a user’s name or avatar to quote, report, bookmark or message them. Type @ and a name to mention someone, and press Ctrl+Enter to send. Have fun! 🐭')],
        ],
        'section' => [
            ['selector' => null, 'title' => __('A section'), 'text' => __('Here are the threads of this section. Let’s see how to read the list.')],
            ['selector' => '.section-head', 'title' => __('Back to all sections'), 'text' => __('Click the section name and icon to go back to the list of sections.')],
            ['selector' => '#left-panel .ui-content', 'title' => __('A thread in the list'), 'text' => __('Each line shows the title, then the number of posts, who wrote the last post and when. 📌 marks a pinned thread (always on top) and 🔒 a locked one (no more replies).')],
            ['selector' => '#left-panel .meta .count', 'title' => __('Post counter colors'), 'text' => __('Yellow: new posts since your last visit. Green: a thread you never opened. Gray: you have read everything.')],
            ['selector' => '#left-panel .panel-head select', 'title' => __('Sorting'), 'text' => __('Sort by latest activity, newest threads, most replies or most viewed.')],
            ['selector' => '#left-panel .panel-head .pager', 'title' => __('Pages'), 'text' => __('Browse the pages of threads. The list remembers its page and scroll position while you read.')],
            ['selector' => '.list-tools .icon-btn:last-child', 'title' => __('Mark all as read'), 'text' => __('Turns every counter of this section gray when you want a fresh start.')],
            ['selector' => '#left-panel .panel-foot .new-thread', 'title' => __('Start a discussion'), 'text' => __('Create a new thread, with an optional prefix and poll.')],
        ],
        'thread' => [
            ['selector' => '.thread-title', 'title' => __('A thread'), 'text' => __('The title can carry a prefix (Question, Guide, Bug…) and a ✅ Solved badge when an answer solved it.')],
            ['selector' => '.panel-right .panel-head .icon-btn', 'title' => __('Follow'), 'text' => __('Follow a thread to be notified of every reply. You automatically follow threads you post in.')],
            ['selector' => '.unread-divider', 'title' => __('Where you stopped'), 'text' => __('This line marks the first message you have not read yet.')],
            ['selector' => '.author-trigger', 'title' => __('User menu'), 'text' => __('Click a user’s avatar or name to see their profile, send a message, quote, edit, report, bookmark or block.')],
            ['selector' => '.thread-post .reactions', 'title' => __('Reactions'), 'text' => __('React to a post with one click; hover a reaction to see who reacted.')],
            ['selector' => '#reply-body', 'title' => __('Replying'), 'text' => __('Use the toolbar or Markdown, @name to mention someone, and Ctrl+Enter to send. When new posts arrive while you read, a green banner lets you load them.')],
        ],
    ];
    $tourDone = auth()->check() ? auth()->user()->hasSeenTour($tourKey) : null;
@endphp
<div x-data="forumTour(@js($tours[$tourKey]), @js($tourDone), @js(route('tour.done', $tourKey)), @js($tourKey))"
     x-on:start-tour.window="start()" x-on:keydown.window="key($event)">
    <template x-if="open">
        <div class="tour-layer">
            <div class="tour-spotlight" :style="spot"></div>
            <div class="tour-card" :style="cardStyle" role="dialog" aria-modal="true" :aria-label="step.title">
                <div class="tour-progress"><span :style="'width:' + ((index + 1) * 100 / steps.length) + '%'"></span></div>
                <div class="tour-step" x-text="(index + 1) + ' / ' + steps.length"></div>
                <h3 x-text="step.title"></h3>
                <p x-text="step.text"></p>
                <div class="tour-actions">
                    <button type="button" class="linkbtn" x-on:click="finish()">{{ __('Skip the tour') }}</button>
                    <span class="spacer"></span>
                    <button type="button" class="btn small" x-show="index > 0" x-on:click="prev()">← {{ __('Back') }}</button>
                    <button type="button" class="btn small primary" x-on:click="next()" x-text="index === steps.length - 1 ? @js(__('Got it!')) : @js(__('Next')) + ' →'"></button>
                </div>
                <a x-show="index === steps.length - 1" class="tour-help" href="{{ route('help') }}" x-on:click="finish()">📖 {{ __('Read the help page') }}</a>
            </div>
        </div>
    </template>
</div>
