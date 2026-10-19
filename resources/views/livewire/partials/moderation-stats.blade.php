@php($duration = fn ($s) => \App\Services\ModerationInsights::duration($s !== null ? (float) $s : null))
<div class="settings-page">
    <div class="settings-card">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <h3 style="margin-right:auto">📊 {{ __('Moderation statistics') }}</h3>
            <select class="select-sm" wire:model.live="days" aria-label="{{ __('Period') }}">
                @foreach ([7, 30, 90, 365] as $d)<option value="{{ $d }}">{{ trans_choice('Last :count day|Last :count days', $d, ['count' => $d]) }}</option>@endforeach
            </select>
        </div>
        <div class="health-grid">
            <div class="health-tile"><div class="k">{{ __('Reports received') }}</div><div class="v">{{ $stats['received'] }}</div></div>
            <div class="health-tile"><div class="k">{{ __('Handled') }}</div><div class="v">{{ $stats['handled'] }}</div></div>
            <div class="health-tile"><div class="k">{{ __('Still open (all time)') }}</div><div class="v">{{ $stats['open'] }}</div></div>
            <div class="health-tile"><div class="k">{{ __('Average handling time') }}</div><div class="v">{{ $duration($stats['avgSeconds']) }}</div></div>
            <div class="health-tile"><div class="k">{{ __('Median handling time') }}</div><div class="v">{{ $duration($stats['medianSeconds']) }}</div></div>
        </div>
        <p class="muted" style="font-size:11px;margin:6px 0 0">{{ __('Only the sections you moderate are counted.') }}</p>
    </div>

    <div class="settings-card">
        <h3>{{ __('By moderator') }}</h3>
        <table class="grid">
            <thead><tr><th>{{ __('Moderator') }}</th><th style="text-align:right">{{ __('Reports handled') }}</th><th style="text-align:right">{{ __('Resolved') }}</th><th style="text-align:right">{{ __('Dismissed') }}</th><th style="text-align:right">{{ __('Average time') }}</th><th style="text-align:right">{{ __('Other actions') }}</th></tr></thead>
            <tbody>
            @forelse ($stats['perModerator'] as $row)
                <tr>
                    <td><x-user-name :user="$row['user']" /></td>
                    <td style="text-align:right">{{ $row['handled'] }}</td>
                    <td style="text-align:right">{{ $row['resolved'] }}</td>
                    <td style="text-align:right">{{ $row['dismissed'] }}</td>
                    <td style="text-align:right">{{ $duration($row['avg']) }}</td>
                    <td style="text-align:right">{{ $row['actions'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">{{ __('No moderation activity in this period.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="stats-columns">
        <div class="settings-card">
            <h3>{{ __('Most reported sections') }}</h3>
            @forelse ($stats['topSections'] as $row)
                <div class="stat-bar"><span class="label">{{ $row['section']->name }}</span><span class="bar"><span style="width: {{ round($row['n'] / max(1, $stats['topSections']->max('n')) * 100) }}%"></span></span><span class="n">{{ $row['n'] }}</span></div>
            @empty
                <div class="empty">{{ __('No reports in this period.') }}</div>
            @endforelse
        </div>
        <div class="settings-card">
            <h3>{{ __('Most reported users') }}</h3>
            @forelse ($stats['topUsers'] as $row)
                <div class="stat-bar">
                    <a class="label" href="{{ route('moderation.users.show', $row['user']) }}" wire:navigate style="color: {{ $row['user']->displayColor() }}">{{ $row['user']->name }}</a>
                    <span class="bar"><span style="width: {{ round($row['n'] / max(1, $stats['topUsers']->max('n')) * 100) }}%"></span></span>
                    <span class="n" title="{{ trans_choice(':count post|:count posts', $row['posts'], ['count' => $row['posts']]) }}">{{ $row['n'] }}</span>
                </div>
            @empty
                <div class="empty">{{ __('No reports in this period.') }}</div>
            @endforelse
        </div>
        <div class="settings-card">
            <h3>{{ __('Actions') }}</h3>
            @forelse ($stats['actions'] as $action => $n)
                <div class="stat-bar"><span class="label"><code>{{ $action }}</code></span><span class="bar"><span style="width: {{ round($n / max(1, $stats['actions']->max()) * 100) }}%"></span></span><span class="n">{{ $n }}</span></div>
            @empty
                <div class="empty">{{ __('No moderation activity in this period.') }}</div>
            @endforelse
        </div>
        <div class="settings-card">
            <h3>{{ __('Sanctions') }}</h3>
            @forelse ($stats['sanctions'] as $type => $n)
                <div class="stat-bar"><span class="label">{{ __(ucfirst($type)) }}</span><span class="bar"><span style="width: {{ round($n / max(1, $stats['sanctions']->max()) * 100) }}%"></span></span><span class="n">{{ $n }}</span></div>
            @empty
                <div class="empty">{{ __('No sanctions.') }}</div>
            @endforelse
        </div>
    </div>
</div>
