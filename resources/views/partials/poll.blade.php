{{-- A poll attached to a post: $poll, $myVotes (option ids), $voterCount, $voterNames --}}
@php
    $hasVoted = ! empty($myVotes);
    $showResults = $poll->resultsVisibleFor($hasVoted);
    $total = max(1, $poll->options->sum('votes_count'));
    $canVote = auth()->check() && auth()->user()->can('vote', $poll) && ($poll->allow_change || ! $hasVoted);
@endphp
<div class="poll-box in-post" wire:key="poll{{ $poll->id }}">
    <div class="poll-question">📊 {{ $poll->question }}</div>
    <div class="poll-meta">
        <span>{{ trans_choice(':count voter|:count voters', $voterCount) }}</span>
        <span>· {{ $poll->multiple ? ($poll->max_choices ? __('up to :count answers', ['count' => $poll->max_choices]) : __('several answers')) : __('one answer') }}</span>
        @if ($poll->closes_at)<span>· {{ $poll->isClosed() ? '🔒 '.__('ended :when', ['when' => $poll->closes_at->diffForHumans()]) : '⏱️ '.__('ends :when', ['when' => $poll->closes_at->diffForHumans()]) }}</span>@endif
        @if ($poll->show_voters)<span>· 👁️ {{ __('public votes') }}</span>@endif
        @unless ($poll->allow_change)<span>· {{ __('final votes') }}</span>@endunless
    </div>
    <form wire:submit="vote({{ $poll->id }})">
        @foreach ($poll->options as $option)
            @php($percent = round($option->votes_count * 100 / $total))
            <label class="poll-option {{ in_array($option->id, $myVotes) ? 'mine' : '' }} {{ $canVote ? '' : 'readonly' }}" wire:key="po{{ $option->id }}">
                @if ($canVote)
                    @if ($poll->multiple)
                        <input type="checkbox" value="{{ $option->id }}" wire:model="ballot.p{{ $poll->id }}">
                    @else
                        <input type="radio" value="{{ $option->id }}" wire:model="ballot.p{{ $poll->id }}" name="poll-{{ $poll->id }}">
                    @endif
                @elseif (in_array($option->id, $myVotes))
                    <span>✓</span>
                @endif
                <span class="poll-label">{{ $option->label }}</span>
                @if ($showResults)
                    <span class="poll-bar"><span style="width: {{ $percent }}%"></span></span>
                    <span class="poll-count" title="{{ trans_choice(':count vote|:count votes', $option->votes_count) }}">{{ $percent }}%</span>
                @endif
            </label>
            @if ($showResults && $poll->show_voters && ! empty($voterNames[$option->id]))
                <div class="poll-voters">{{ implode(', ', array_slice($voterNames[$option->id], 0, 30)) }}{{ count($voterNames[$option->id]) > 30 ? '…' : '' }}</div>
            @endif
        @endforeach
        @unless ($showResults)
            <div class="muted poll-hidden-note">{{ $poll->results_visibility === 'after_vote' ? __('Results are shown once you voted.') : __('Results are shown when the poll ends.') }}</div>
        @endunless
        @error('pollChoices')<div class="error">{{ $message }}</div>@enderror
        <div class="poll-actions">
            @if ($canVote)<button type="submit" class="btn small primary">{{ $hasVoted ? __('Change my vote') : __('Vote') }}</button>@endif
            @if ($hasVoted && $poll->allow_change && ! $poll->isClosed())<button type="button" class="btn small" wire:click="retractVote({{ $poll->id }})">{{ __('Remove my vote') }}</button>@endif
            @can('close', $poll)<button type="button" class="linkbtn" x-data x-on:click="$confirm(@js(__('Nobody will be able to vote anymore.')), () => $wire.closePoll({{ $poll->id }}), { title: @js(__('End the poll now?')), label: @js(__('End')), danger: false })">{{ __('End the poll') }}</button>@endcan
        </div>
    </form>
</div>
