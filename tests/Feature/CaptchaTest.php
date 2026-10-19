<?php

use App\Livewire\AuthModal;
use App\Models\User;
use App\Services\CaptchaService;
use Livewire\Livewire;

beforeEach(fn () => config(['forum.captcha' => true]));

function registration(array $overrides = []): array
{
    return $overrides + ['name' => 'newmouse', 'email' => 'new@test.dev', 'password' => 'long-password-1', 'password_confirmation' => 'long-password-1'];
}

it('serves a fresh png image and stores the code in the session', function () {
    $response = $this->get(route('captcha'))->assertOk()->assertHeader('Content-Type', 'image/png');

    expect(substr($response->getContent(), 1, 3))->toBe('PNG')
        ->and(session('captcha.hash'))->toBeString()
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('registers only with the right code, case-insensitive', function () {
    $code = app(CaptchaService::class)->generate();
    $this->post('/register', registration(['captcha' => 'WRONG']))->assertSessionHasErrors('captcha');
    expect(User::where('name', 'newmouse')->exists())->toBeFalse();

    $code = app(CaptchaService::class)->generate();
    $this->post('/register', registration(['captcha' => strtolower($code)]))->assertRedirect(route('home'));
    expect(User::where('name', 'newmouse')->exists())->toBeTrue();
});

it('accepts each code only once', function () {
    $code = app(CaptchaService::class)->generate();

    expect(app(CaptchaService::class)->check($code))->toBeTrue()
        ->and(app(CaptchaService::class)->check($code))->toBeFalse();
});

it('refuses expired codes', function () {
    $code = app(CaptchaService::class)->generate();
    session(['captcha.expires' => time() - 1]);

    expect(app(CaptchaService::class)->check($code))->toBeFalse();
});

it('protects the registration modal and refreshes the image after an attempt', function () {
    $component = Livewire::test(AuthModal::class)
        ->set('regName', 'modalmouse')->set('regEmail', 'm@test.dev')
        ->set('regPassword', 'long-password-1')->set('regPassword_confirmation', 'long-password-1')
        ->set('captcha', 'NOPE1')
        ->call('register')
        ->assertHasErrors('captcha')
        ->assertDispatched('captcha-refresh');
    expect(User::where('name', 'modalmouse')->exists())->toBeFalse();

    $code = app(CaptchaService::class)->generate();
    $component->set('captcha', $code)->call('register')->assertHasNoErrors()->assertRedirect();
    expect(User::where('name', 'modalmouse')->exists())->toBeTrue();
});

it('rejects bots filling the hidden honeypot field', function () {
    $code = app(CaptchaService::class)->generate();
    $this->post('/register', registration(['captcha' => $code, 'website' => 'http://spam.example']))->assertSessionHasErrors('name');

    Livewire::test(AuthModal::class)->set('website', 'spam')->set('regName', 'botmouse')->call('register')->assertHasErrors('regName');
    expect(User::count())->toBe(0);
});

it('shows the captcha on both registration forms', function () {
    $this->get(route('register'))->assertSeeHtml('captcha.png')->assertSee('Security check');
    Livewire::test(AuthModal::class)->assertSee('Security check');
});
