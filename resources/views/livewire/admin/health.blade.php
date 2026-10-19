<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head">
        <h1 class="with-art"><x-art name="health" /> {{ __('System health') }}</h1>
        <button type="button" class="icon-btn" wire:click="$refresh" title="{{ __('Refresh') }}">↻ {{ __('Refresh') }}</button>
    </div>
    <div class="scroll settings-page">
        
        <div class="settings-card">
            <h3>{{ __('Checks') }}</h3>
            @foreach ($checks as $check)
                <div class="health-check">
                    <span class="dot {{ $check['level'] }}" title="{{ ['ok' => __('OK'), 'warning' => __('Warning'), 'error' => __('Problem')][$check['level']] }}"></span>
                    <div><strong>{{ $check['label'] }}</strong><span class="muted">{{ $check['detail'] }}</span></div>
                </div>
            @endforeach
            <div class="settings-actions" style="gap:8px">
                <button type="button" class="btn" wire:click="runAudit" wire:loading.attr="disabled" wire:target="runAudit">
                    <span wire:loading.remove wire:target="runAudit">🔍 {{ __('Run the security audit') }}</span>
                    <span wire:loading wire:target="runAudit">{{ __('Checking dependencies…') }}</span>
                </button>
            </div>
        </div>

        <div class="settings-card">
            <h3>{{ __('Versions') }}</h3>
            <div class="health-grid">
                @foreach ($versions as $label => $value)
                    <div class="health-tile"><div class="k">{{ $label }}</div><div class="v">{{ $value }}</div></div>
                @endforeach
            </div>
        </div>

        <div class="settings-card">
            <h3>{{ __('Database') }}</h3>
            <div class="health-grid">
                <div class="health-tile"><div class="k">{{ __('Total size') }}</div><div class="v">{{ $health->bytes($db['size']) }}</div></div>
                <div class="health-tile"><div class="k">{{ __('Connections') }}</div><div class="v">{{ $db['connections'] }}</div></div>
                @foreach ($db['counts'] as $label => $count)
                    <div class="health-tile"><div class="k">{{ $label }}</div><div class="v">{{ number_format($count, 0, '', ' ') }}</div></div>
                @endforeach
            </div>
            <table class="grid" style="margin-top:8px">
                <thead><tr><th>{{ __('Largest tables') }}</th><th style="text-align:right">{{ __('Rows') }}</th><th style="text-align:right">{{ __('Size') }}</th></tr></thead>
                <tbody>
                    @foreach ($db['tables'] as $table)
                        <tr><td><code>{{ $table['name'] }}</code></td><td style="text-align:right">{{ number_format($table['rows'], 0, '', ' ') }}</td><td style="text-align:right">{{ $health->bytes($table['size']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form class="settings-card" wire:submit="sendTestMail">
            <h3>{{ __('Test email') }}</h3>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Send to') }}</strong><span>{{ __('Checks that the forum can send emails (confirmations, password resets).') }}</span></div>
                <div class="settings-field"><input type="email" wire:model="mailTo" maxlength="255">@error('mailTo')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="settings-actions"><button type="submit" class="btn primary">✉️ {{ __('Send a test email') }}</button></div>
        </form>
    </div>
</div>
