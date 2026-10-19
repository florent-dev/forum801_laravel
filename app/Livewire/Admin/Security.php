<?php

namespace App\Livewire\Admin;

use App\Models\IpBan;
use App\Models\ModerationLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\IpUtils;

class Security extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'events';

    #[Url]
    public string $type = '';

    #[Url]
    public string $severity = '';

    #[Url]
    public string $ip = '';

    #[Url]
    public string $user = '';

    public string $banIp = '';

    public string $banReason = '';

    public string $banDays = '';

    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function updating($name): void
    {
        if (in_array($name, ['type', 'severity', 'ip', 'user', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function addBan(SecurityService $security): void
    {
        $this->authorize('admin');
        $this->validate([
            'banIp' => ['required', 'string', 'max:64', function ($attribute, $value, $fail) {
                [$address, $mask] = array_pad(explode('/', $value, 2), 2, null);
                $valid = filter_var($address, FILTER_VALIDATE_IP) && ($mask === null || (ctype_digit($mask) && (int) $mask <= (str_contains($address, ':') ? 128 : 32)));
                if (! $valid) {
                    $fail(__('Enter an IP address or a range like 203.0.113.0/24.'));
                }
            }],
            'banReason' => ['required', 'string', 'min:3', 'max:500'],
            'banDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);
        // Never lock the current administrator out
        if (IpUtils::checkIp((string) request()->ip(), $this->banIp)) {
            $this->addError('banIp', __('This range contains your own address.'));

            return;
        }
        $ban = IpBan::create([
            'ip' => $this->banIp,
            'reason' => $this->banReason,
            'created_by' => auth()->id(),
            'expires_at' => $this->banDays !== '' ? now()->addDays((int) $this->banDays) : null,
        ]);
        $security->forgetBanCache();
        $security->record('ip_ban_added', auth()->user(), ['ip' => $ban->ip, 'reason' => $ban->reason]);
        ModerationLog::record(auth()->user(), 'ip.ban', auth()->user(), null, ['ip' => $ban->ip, 'reason' => $ban->reason]);
        $this->reset('banIp', 'banReason', 'banDays');
        $this->dispatch('toast', message: __('Address banned.'));
    }

    public function removeBan(int $id, SecurityService $security): void
    {
        $this->authorize('admin');
        $ban = IpBan::findOrFail($id);
        $ban->delete();
        $security->forgetBanCache();
        $security->record('ip_ban_removed', auth()->user(), ['ip' => $ban->ip]);
        $this->dispatch('toast', message: __('Ban lifted.'));
    }

    public function banAddress(string $ip): void
    {
        $this->tab = 'bans';
        $this->banIp = $ip;
    }

    public function render(SecurityService $security)
    {
        $this->authorize('admin');
        $data = ['types' => array_keys(SecurityEvent::TYPES)];

        if ($this->tab === 'events') {
            $data['events'] = SecurityEvent::with('user.roles')
                ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
                ->when($this->severity !== '', fn ($q) => $q->where('severity', $this->severity))
                ->when($this->ip !== '', fn ($q) => $q->where('ip', $this->ip))
                ->when($this->user !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', $this->user)))
                ->latest('id')->paginate(40);
        } elseif ($this->tab === 'lookup') {
            $data['accounts'] = $this->ip !== '' ? $security->accountsForIp($this->ip) : collect();
            $data['ipEvents'] = $this->ip !== '' ? SecurityEvent::with('user')->where('ip', $this->ip)->latest('id')->limit(20)->get() : collect();
            $data['ipBan'] = $this->ip !== '' ? IpBan::matching($this->ip) : null;
        } elseif ($this->tab === 'suspicious') {
            $data['failedByIp'] = SecurityEvent::where('type', 'login_failed')->where('created_at', '>', now()->subDay())
                ->select('ip', DB::raw('count(*) as attempts'), DB::raw('count(distinct details->>\'name\') as names'), DB::raw('max(created_at) as last_at'))
                ->groupBy('ip')->havingRaw('count(*) >= 3')->orderByDesc('attempts')->limit(30)->get();
            $data['crowdedIps'] = DB::table('user_ips')->where('last_seen_at', '>', now()->subDays(30))
                ->select('ip', DB::raw('count(distinct user_id) as accounts'))
                ->groupBy('ip')->havingRaw('count(distinct user_id) >= 3')->orderByDesc('accounts')->limit(30)->get();
            $data['evasions'] = SecurityEvent::with('user')->where('type', 'ban_evasion_suspected')->latest('id')->limit(20)->get();
        } else {
            $data['bans'] = IpBan::with('creator')->latest('id')->get();
        }

        return view('livewire.admin.security', $data);
    }

    public function paginationView(): string
    {
        return 'partials.pager';
    }
}
