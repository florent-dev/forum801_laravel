<div style="display:flex;flex-direction:column;min-height:0;flex:1" @if (in_array($tab, ['deleted', 'logs'], true)) wire:poll.20s @endif>
    <div class="panel-head">
        <div class="tabs">
            <a href="#" class="{{ $tab === 'deleted' ? 'active' : '' }}" wire:click.prevent="setTab('deleted')">{{ __('Deleted threads') }}</a>
            <a href="#" class="{{ $tab === 'logs' ? 'active' : '' }}" wire:click.prevent="setTab('logs')">{{ __('Moderation log') }}</a>
            <a href="#" class="{{ $tab === 'search' ? 'active' : '' }}" wire:click.prevent="setTab('search')">🔎 {{ __('Search') }}</a>
            <a href="#" class="{{ $tab === 'stats' ? 'active' : '' }}" wire:click.prevent="setTab('stats')">📊 {{ __('Statistics') }}</a>
        </div>
        @if ($tab === 'deleted')
            {{ $deleted->links() }}
        @elseif ($tab === 'logs')
            {{ $logs->links() }}
        @elseif ($tab === 'search' && $results)
            {{ $results->links() }}
        @endif
    </div>
    <div class="scroll">
        @if (in_array($tab, ['deleted', 'logs'], true))
            <div class="empty" style="padding:10px">👈 {{ __('Pick a report on the left to review the reported post in its thread.') }}</div>
        @endif
        @if ($tab === 'search')
            @include('livewire.partials.moderation-search')
        @elseif ($tab === 'stats')
            @include('livewire.partials.moderation-stats')
        @elseif ($tab === 'deleted')
            @if ($deleted->isNotEmpty())
                <div class="bulk-bar" style="position:static">
                    <span>{!! __(':count selected', ['count' => '<strong>'.count($selected).'</strong>']) !!}</span>
                    <button type="button" class="btn small primary" wire:click="restoreThreads(@js($selected))" @disabled(! $selected)>♻️ {{ __('Restore selected') }}</button>
                    <button type="button" class="linkbtn" x-on:click="$wire.set('selected', [...document.querySelectorAll('.thread-check')].map(c => c.value))">{{ __('All') }}</button>
                    <button type="button" class="linkbtn" wire:click="$set('selected', [])">{{ __('None') }}</button>
                </div>
            @endif
            <table class="grid">
                <thead><tr><th></th><th>{{ __('Thread') }}</th><th>{{ __('Section') }}</th><th>{{ __('Author') }}</th><th>{{ __('Deleted') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($deleted as $thread)
                    <tr wire:key="d{{ $thread->id }}">
                        <td><input type="checkbox" class="thread-check" value="{{ $thread->id }}" wire:model.live="selected" aria-label="{{ __('Select') }}"></td>
                        <td><a href="{{ $thread->url() }}" wire:navigate>{{ $thread->title }}</a> <span class="muted">({{ trans_choice(':count post|:count posts', $thread->posts_count) }})</span></td>
                        <td>{{ $thread->section->name }}</td>
                        <td><x-user-name :user="$thread->user" /></td>
                        <td class="muted">{{ $thread->deleted_at->diffForHumans() }}{{ $thread->deleter ? ' '.__('by').' '.$thread->deleter->name : '' }}</td>
                        <td><button class="btn small primary" wire:click="restoreThreads([{{ $thread->id }}])">{{ __('Restore') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ __('No deleted threads.') }} 🎉</td></tr>
                @endforelse
                </tbody>
            </table>
        @else
            <table class="grid">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Moderator') }}</th><th>{{ __('Action') }}</th><th>{{ __('Section') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Details') }}</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr wire:key="l{{ $log->id }}">
                        <td class="muted">{{ $log->created_at?->shortDateTime() }}</td>
                        <td><x-user-name :user="$log->moderator" /></td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->section?->name ?? '—' }}</td>
                        <td>{{ $log->subject_type }} #{{ $log->subject_id }}</td>
                        <td class="muted">@foreach ((array) $log->details as $k => $v){{ $k }}: {{ is_scalar($v) ? $v : json_encode($v) }}@if (! $loop->last), @endif @endforeach</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ __('No actions logged yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>
