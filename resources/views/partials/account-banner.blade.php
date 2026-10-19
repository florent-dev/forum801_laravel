{{-- Maintenance notices for everyone, then account notices for members --}}
@php($maintenanceState = app(\App\Services\MaintenanceService::class)->state())
@if ($maintenanceState['enabled'])
    <div class="ban-banner maintenance-banner">🛠️ {{ __('Maintenance mode is ON: only staff can use the forum.') }} @can('admin')<a href="{{ route('admin.maintenance') }}" wire:navigate>{{ __('Manage') }}</a>@endcan</div>
@elseif ($maintenanceState['scheduled_at'] && \Illuminate\Support\Carbon::parse($maintenanceState['scheduled_at'])->isFuture())
    <div class="ban-banner verify">📣 {{ __('A maintenance is planned :when.', ['when' => \Illuminate\Support\Carbon::parse($maintenanceState['scheduled_at'])->diffForHumans()]) }} {{ $maintenanceState['message'] }}</div>
@endif
{{-- Tells members why they cannot take part right now --}}
@auth
    @php($me = auth()->user())
    @if ($suspension = $me->activeSanction('suspension'))
        <div class="ban-banner">
            {{ $suspension->expires_at ? __('Your account is suspended until :date.', ['date' => $suspension->expires_at->shortDateTime()]) : __('Your account is suspended.') }}
            {{ __('Reason: :reason', ['reason' => $suspension->reason]) }}
        </div>
    @elseif ($mute = $me->activeSanction('mute'))
        <div class="ban-banner mute">
            {{ $mute->expires_at ? __('You are muted until :date and cannot post or send messages.', ['date' => $mute->expires_at->shortDateTime()]) : __('You are muted and cannot post or send messages.') }}
            {{ __('Reason: :reason', ['reason' => $mute->reason]) }}
        </div>
    @elseif (! $me->hasVerifiedEmail())
        <div class="ban-banner verify">
            ✉️ {{ __('Please confirm your email address to create threads, reply and send messages. We sent a link to :email.', ['email' => $me->email]) }}
            <form method="POST" action="{{ route('verification.send') }}" style="display:inline">@csrf<button type="submit" class="linkbtn">{{ __('Send the link again') }}</button></form>
        </div>
    @endif
@endauth
