<x-layouts.app :title="__('Help')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="panel-head">
        <h1 class="with-art"><x-art name="help" /> {{ __('Help & tips') }}</h1>
        <button type="button" class="btn small primary tour-trigger" x-data x-on:click="$dispatch('start-tour')">❓ {{ __('Replay the tour') }}</button>
    </div>
    <div class="scroll settings-page">
        @foreach ([
            ['🧭', __('Finding your way'), [
                __('Sections of your community are on the left. Click the section name at the top of a thread list to come back to all sections.'),
                __('The flag at the top switches community: the sections and the interface language change with it.'),
                __('Threads you already read are gray. A dot and “first unread »” take you straight to what is new.'),
            ]],
            ['✍️', __('Writing'), [
                __('The editor understands Markdown: **bold**, *italic*, > quotes, lists, links and images. Use Preview before sending.'),
                __('Type @ followed by a nickname to mention a user; they get a notification if their settings allow it.'),
                __('Hide a spoiler with the crossed-out eye button or [spoiler]text[/spoiler]: readers click it to reveal it.'),
                __('Ctrl+Enter sends your message. Unsent text is kept in your browser if you leave the page.'),
                __('You can add a poll and a prefix (Question, Guide, Bug…) when you create a thread.'),
            ]],
            ['👥', __('Users'), [
                __('Click a user’s name or avatar in a thread to open their menu: profile, message, quote, report, bookmark.'),
                __('Add friends from their profile, and ignore users you do not want to read anymore.'),
                __('In Settings › Privacy you can hide your online status and choose who can mention you.'),
            ]],
            ['🔔', __('Staying up to date'), [
                __('You automatically follow threads you post in. Use ☆ Follow on any other thread.'),
                __('The bell lists replies, mentions, quotes, messages and friend requests.'),
                __('Mark a section or everything as read when you want a fresh start.'),
            ]],
            ['🛡️', __('Rules & moderation'), [
                __('Report a post from its author menu when it breaks the rules; moderators review every report.'),
                __('A moderated post shows the moderator name and reason instead of its content.'),
                __('Warnings, mutes and suspensions are explained in a banner at the top of the page.'),
            ]],
        ] as [$icon, $title, $items])
            <section class="settings-card help-card">
                <h3>{{ $icon }} {{ $title }}</h3>
                <ul>
                    @foreach ($items as $item)<li>{{ $item }}</li>@endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-layouts.app>
