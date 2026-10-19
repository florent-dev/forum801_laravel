<div class="report-panel">
    <div class="report-panel-main">
        <div>
            <strong>🚩 {{ __('Report #:id', ['id' => $report->id]) }}</strong>
            <span class="muted">· {{ $report->created_at->diffForHumans() }} · {{ $report->reporter_id ? __('by') : '' }}</span> @if ($report->reporter_id)<x-user-name :user="$report->reporter" />@else<span class="muted">{{ __('automatic word filter') }}</span>@endif
            @if ($sameCount)<span class="pill" style="color:var(--warn)">{{ trans_choice(':count other open report on this post|:count other open reports on this post', $sameCount) }}</span>@endif
        </div>
        <div class="report-reason big">“{{ $report->reason }}”</div>
        @if ($report->status !== 'open')
            <div class="muted" style="font-size:10px">{{ __(ucfirst($report->status)) }} · {{ $report->handler?->name ?? '—' }} · {{ $report->handled_at?->diffForHumans() }}</div>
        @endif
    </div>
    @if ($report->status === 'open')
        <div class="mod-actions">
            <button class="btn small" wire:click="moderateAndResolve" wire:loading.attr="disabled" title="{{ __('The post stays visible with the report reason as moderation note') }}">⚖️ {{ __('Moderate & resolve') }}</button>
            <button class="btn small danger" wire:click="hideAndResolve" wire:loading.attr="disabled">🙈 {{ __('Hide post & resolve') }}</button>
            <button class="btn small" wire:click="toggleLock">{{ $report->post->thread->is_locked ? '🔓 '.__('Unlock thread') : '🔒 '.__('Lock thread') }}</button>
            <button class="btn small primary" wire:click="resolve('resolved')" wire:loading.attr="disabled">✓ {{ __('Resolve') }}</button>
            <button class="btn small" wire:click="resolve('dismissed')" wire:loading.attr="disabled">✕ {{ __('Dismiss') }}</button>
        </div>
    @endif
</div>
