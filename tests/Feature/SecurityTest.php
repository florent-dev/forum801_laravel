<?php

use App\Livewire\Admin\Security;
use App\Livewire\AuthModal;
use App\Livewire\IpInfoModal;
use App\Models\IpBan;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\Section;
use App\Models\User;
use App\Services\ForumService;
use App\Services\SecurityService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('records logins, failures, logouts and registrations', function () {
    User::factory()->create(['name' => 'secure', 'password' => 'right-password']);

    $this->post('/login', ['name' => 'secure', 'password' => 'wrong']);
    $this->post('/login', ['name' => 'secure', 'password' => 'right-password']);
    $this->post('/logout');
    Livewire::test(AuthModal::class)->set('regName', 'newbie')->set('regEmail', 'n@test.dev')
        ->set('regPassword', 'long-password-1')->set('regPassword_confirmation', 'long-password-1')->call('register');

    expect(SecurityEvent::pluck('type')->all())->toContain('login_failed', 'login', 'logout', 'register')
        ->and(SecurityEvent::where('type', 'login_failed')->first()->details['name'])->toBe('secure')
        ->and(User::where('name', 'newbie')->value('registration_ip'))->toBe('127.0.0.1');
});

it('records lockouts', function () {
    foreach (range(1, 6) as $i) {
        $this->post('/login', ['name' => 'target', 'password' => 'x'.$i]);
    }

    expect(SecurityEvent::where('type', 'lockout')->where('severity', 'critical')->exists())->toBeTrue();
});

it('tracks the addresses of members and of posts', function () {
    $user = userWithRole(Role::MEMBER);

    $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->get('/');
    $thread = app(ForumService::class)->createThread($user, Section::factory()->create(), 'Ip', 'Hello');

    expect($user->fresh()->last_ip)->toBe('198.51.100.7')
        ->and(DB::table('user_ips')->where('user_id', $user->id)->value('ip'))->toBe('198.51.100.7')
        ->and($thread->posts()->first()->ip)->not->toBeNull()
        ->and($thread->posts()->first()->toArray())->not->toHaveKey('ip');
});

it('flags accounts sharing an address with a suspended one', function () {
    $banned = sanction(userWithRole(Role::MEMBER));
    DB::table('user_ips')->insert(['user_id' => $banned->id, 'ip' => '203.0.113.9', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $alt = userWithRole(Role::MEMBER);

    app(SecurityService::class)->trackIp($alt, '203.0.113.9');

    $event = SecurityEvent::where('type', 'ban_evasion_suspected')->first();
    expect($event->user_id)->toBe($alt->id)->and($event->details['shared_with'])->toBe([$banned->name]);
});

it('blocks banned addresses from writing but lets them read', function () {
    IpBan::create(['ip' => '192.0.2.0/24', 'reason' => 'Spam wave']);
    app(SecurityService::class)->forgetBanCache();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.55'])->get('/')->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.55'])->post('/login', ['name' => 'x', 'password' => 'y'])->assertForbidden();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.55'])->get('/register')->assertForbidden();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->get('/register')->assertOk();

    expect(SecurityEvent::where('type', 'banned_ip_blocked')->exists())->toBeTrue();
});

it('lets temporary ip bans expire', function () {
    IpBan::create(['ip' => '192.0.2.10', 'reason' => 'old', 'expires_at' => now()->subMinute()]);

    expect(IpBan::matching('192.0.2.10'))->toBeNull();
});

it('manages ip bans from the security page', function () {
    $admin = userWithRole(Role::ADMINISTRATOR);

    Livewire::actingAs($admin)->test(Security::class)->set('tab', 'bans')
        ->set('banIp', 'not-an-ip')->set('banReason', 'Nope')->call('addBan')->assertHasErrors('banIp');
    Livewire::actingAs($admin)->test(Security::class)->set('tab', 'bans')
        ->set('banIp', '127.0.0.0/8')->set('banReason', 'Self lock')->call('addBan')->assertHasErrors('banIp');
    Livewire::actingAs($admin)->test(Security::class)->set('tab', 'bans')
        ->set('banIp', '203.0.113.0/24')->set('banReason', 'Bots')->set('banDays', '7')->call('addBan')->assertHasNoErrors();

    $ban = IpBan::first();
    expect($ban->ip)->toBe('203.0.113.0/24')->and($ban->expires_at->isFuture())->toBeTrue()
        ->and(SecurityEvent::where('type', 'ip_ban_added')->exists())->toBeTrue();

    Livewire::actingAs($admin)->test(Security::class)->call('removeBan', $ban->id);
    expect(IpBan::count())->toBe(0);
});

it('shows events, lookups and suspicious activity to administrators only', function () {
    $admin = userWithRole(Role::ADMINISTRATOR);
    $user = userWithRole(Role::MEMBER);
    DB::table('user_ips')->insert(['user_id' => $user->id, 'ip' => '198.51.100.20', 'hits' => 3, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    app(SecurityService::class)->record('login_failed', null, ['name' => 'someone']);

    Livewire::actingAs($admin)->test(Security::class)->assertSee('login_failed');
    Livewire::actingAs($admin)->test(Security::class)->set('tab', 'lookup')->set('ip', '198.51.100.20')->assertSee($user->name);
    Livewire::actingAs($admin)->test(Security::class)->set('tab', 'suspicious')->assertOk();
    $this->actingAs($admin)->get(route('admin.security'))->assertOk();

    Livewire::actingAs(userWithRole(Role::SENIOR_MODERATOR))->test(Security::class)->assertForbidden();
});

it('shows ip information to senior staff only', function () {
    $user = userWithRole(Role::MEMBER);
    $other = userWithRole(Role::MEMBER);
    foreach ([$user, $other] as $u) {
        DB::table('user_ips')->insert(['user_id' => $u->id, 'ip' => '198.51.100.30', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    Livewire::actingAs(userWithRole(Role::SENIOR_MODERATOR))->test(IpInfoModal::class)->call('open', $user->id)
        ->assertSee('198.51.100.30')->assertSee($other->name);
    Livewire::actingAs(userWithRole(Role::MODERATOR, Section::factory()->create()))->test(IpInfoModal::class)->call('open', $user->id)->assertForbidden();
});

it('records password and email changes', function () {
    $user = User::factory()->create(['password' => 'the-password']);

    $this->actingAs($user)->put(route('settings.password'), ['current_password' => 'the-password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1']);
    $this->actingAs($user)->put(route('settings.email'), ['current_password' => 'new-password-1', 'email' => 'changed@test.dev']);

    expect(SecurityEvent::where('user_id', $user->id)->pluck('type')->all())->toContain('password_changed', 'email_changed');
});

it('purges old addresses and events', function () {
    $user = userWithRole(Role::MEMBER);
    DB::table('user_ips')->insert(['user_id' => $user->id, 'ip' => '198.51.100.40', 'hits' => 1, 'first_seen_at' => now()->subYear(), 'last_seen_at' => now()->subYear()]);
    SecurityEvent::create(['type' => 'login'])->forceFill(['created_at' => now()->subYear()])->save();
    SecurityEvent::create(['type' => 'login']);

    $this->artisan('forum:prune-security', ['--days' => 30])->assertSuccessful();

    expect(DB::table('user_ips')->count())->toBe(0)->and(SecurityEvent::count())->toBe(1);
});
