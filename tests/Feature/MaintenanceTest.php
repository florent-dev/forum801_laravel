<?php

use App\Livewire\Admin\Maintenance;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\Section;
use App\Services\MaintenanceService;
use Livewire\Livewire;

it('closes the forum to visitors and members but not to administrators', function () {
    app(MaintenanceService::class)->enable(null, 'Server upgrade', now()->addHour(), false);

    $this->get('/')->assertStatus(503)->assertSee('Server upgrade')->assertHeader('Retry-After');
    $this->actingAs(userWithRole(Role::MEMBER))->get('/')->assertStatus(503);
    $this->actingAs(userWithRole(Role::MODERATOR, Section::factory()->create()))->get('/')->assertStatus(503);
    $this->actingAs(userWithRole(Role::ADMINISTRATOR))->get('/')->assertOk()->assertSee('Maintenance mode is ON');
});

it('hides the login page unless the secret staff link was used', function () {
    $maintenance = app(MaintenanceService::class);
    $maintenance->enable(null, null, null, false);

    $this->get(route('login'))->assertStatus(503)->assertDontSee('Staff login');
    $this->post('/login', ['name' => 'x', 'password' => 'y'])->assertStatus(503);
    $this->get('/staff/wrong-token')->assertNotFound();

    $this->get(route('maintenance.access', $maintenance->secret()))->assertRedirect(route('login'));
    $this->get(route('login'))->assertOk()->assertSee('Staff login');
    expect(SecurityEvent::where('type', 'maintenance_access')->exists())->toBeTrue();
});

it('invalidates the old staff link when regenerated', function () {
    $maintenance = app(MaintenanceService::class);
    $maintenance->enable(null, null, null, false);
    $old = $maintenance->secret();

    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(Maintenance::class)->call('regenerateSecret');

    expect($maintenance->secret())->not->toBe($old);
    $this->get(route('maintenance.access', $old))->assertNotFound();
});

it('keeps the same staff link when maintenance settings are updated', function () {
    $maintenance = app(MaintenanceService::class);
    $maintenance->enable(null, 'First', null, false);
    $secret = $maintenance->secret();
    $maintenance->enable(null, 'Updated message', null, true);

    expect($maintenance->secret())->toBe($secret);
});

it('can let moderators in', function () {
    app(MaintenanceService::class)->enable(null, null, null, true);

    $this->actingAs(userWithRole(Role::SENIOR_MODERATOR))->get('/')->assertOk();
    $this->actingAs(userWithRole(Role::MEMBER))->get('/')->assertStatus(503);
});

it('is switched from the admin page and logged', function () {
    $admin = userWithRole(Role::ADMINISTRATOR);

    Livewire::actingAs($admin)->test(Maintenance::class)->set('message', 'Back soon')->call('enable');
    expect(app(MaintenanceService::class)->isEnabled())->toBeTrue()
        ->and(SecurityEvent::where('type', 'maintenance_on')->where('user_id', $admin->id)->exists())->toBeTrue();

    Livewire::actingAs($admin)->test(Maintenance::class)->call('disable');
    expect(app(MaintenanceService::class)->isEnabled())->toBeFalse();
    $this->get('/')->assertOk();
});

it('validates the expected end and refuses non administrators', function () {
    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(Maintenance::class)->set('until', '2000-01-01T10:00')->call('enable')->assertHasErrors('until');
    Livewire::actingAs(userWithRole(Role::SENIOR_MODERATOR))->test(Maintenance::class)->assertForbidden();
    $this->actingAs(userWithRole(Role::SENIOR_MODERATOR))->get(route('admin.maintenance'))->assertForbidden();
});

it('announces an upcoming maintenance to everyone', function () {
    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(Maintenance::class)
        ->set('scheduledAt', now()->addDay()->format('Y-m-d\TH:i'))->set('message', 'Big update')->call('schedule');
    auth()->logout();

    $this->get('/')->assertOk()->assertSee('A maintenance is planned')->assertSee('Big update');
});

it('is controlled from the command line', function () {
    $this->artisan('forum:maintenance', ['action' => 'on', '--message' => 'CLI test'])->expectsOutputToContain('Maintenance mode is ON')->expectsOutputToContain('/staff/')->assertSuccessful();
    $this->get('/')->assertStatus(503)->assertSee('CLI test');

    $this->artisan('forum:maintenance', ['action' => 'off'])->expectsOutputToContain('The forum is open')->assertSuccessful();
    $this->get('/')->assertOk();
});

it('lets an administrator log in through the secret link during maintenance', function () {
    $maintenance = app(MaintenanceService::class);
    $maintenance->enable(null, null, null, false);
    \App\Models\User::factory()->create(['name' => 'boss', 'password' => 'boss-password'])->assignRole(Role::ADMINISTRATOR);

    $this->get(route('maintenance.access', $maintenance->secret()));
    $this->get(route('login'))->assertOk()->assertSee('Staff login')->assertDontSeeLivewire('section-list');
    $this->post('/login', ['name' => 'boss', 'password' => 'boss-password'])->assertRedirect(route('home'));
    $this->assertAuthenticated();
    $this->get('/')->assertOk();
});

it('logs members in through the link but still shows them the maintenance page', function () {
    $maintenance = app(MaintenanceService::class);
    $maintenance->enable(null, null, null, false);
    \App\Models\User::factory()->create(['name' => 'player', 'password' => 'player-password']);

    $this->get(route('maintenance.access', $maintenance->secret()));
    $this->post('/login', ['name' => 'player', 'password' => 'player-password'])->assertRedirect(route('home'));
    $this->get('/')->assertStatus(503);
    $this->post('/logout')->assertRedirect(route('home'));
    $this->assertGuest();
});
