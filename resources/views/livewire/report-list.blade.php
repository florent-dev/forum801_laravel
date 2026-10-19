<div style="display:flex;flex-direction:column;min-height:0;flex:1" wire:poll.20s>
    <div class="panel-head">
        <h2 class="with-art"><x-art name="reports" size="18" /> {{ __('Reports') }}</h2>
        {{ $reports->links() }}
    </div>
    <div class="list-tools">
        <div class="tabs">
            @foreach ($statuses as $key => $label)
                <a href="#" class="{{ $status === $key ? 'active' : '' }}" wire:click.prevent="$set('status', '{{ $key }}')">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <div class="scroll" data-section="reports-{{ $status }}-{{ $reports->currentPage() }}">
        @forelse ($reports as $report)
            @php($post = $report->post)
            <a wire:key="rep{{ $report->id }}" href="{{ route('moderation.reports.show', $report) }}" wire:navigate
               class="ui-content report-item {{ $report->id === $activeReportId ? 'selected' : '' }} {{ $report->status !== 'open' ? 'read' : '' }}">
                <div class="row">
                    <span class="title">
                        @if ($report->status === 'open')<span class="unread-dot" style="background:var(--danger)"></span>@endif
                        {{ $post?->thread?->title ?? __('Deleted post') }}
                    </span>
                    <span class="muted" style="white-space:nowrap">{{ $report->created_at->diffForHumans(short: true) }}</span>
                </div>
                <div class="report-reason">“{{ Str::limit($report->reason, 90) }}”</div>
                <div class="meta">
                    <span>{{ $post?->thread?->section?->name }}</span>
                    @if ($post?->user)<span>· <span style="color: {{ $post->user->displayColor() }}">{{ $post->user->name }}</span></span>@endif
                    @if ($report->reporter_id)<span>· {{ __('by') }} <span style="color: {{ $report->reporter?->displayColor() }}">{{ $report->reporter?->name }}</span></span>@else<span>· {{ __('automatic word filter') }}</span>@endif
                    @if ($post?->trashed())<span class="pill" style="color:var(--muted)">{{ __('deleted') }}</span>@elseif ($post?->is_hidden)<span class="pill" style="color:var(--danger)">{{ __('hidden') }}</span>@endif
                    @if ($report->status !== 'open')<span class="pill">{{ __(ucfirst($report->status)) }}</span>@endif
                </div>
            </a>
        @empty
            <div class="empty">{{ __('No reports here.') }} 🎉</div>
        @endforelse
    </div>
</div>
