<?php

use App\Livewire\AuthModal;
use App\Livewire\SectionList;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\ForumService;
use App\Support\Community;
use Livewire\Livewire;

beforeEach(function () {
    $this->en = Section::factory()->create(['name' => 'English corner', 'community' => 'en']);
    $this->fr = Section::factory()->create(['name' => 'Coin francophone', 'community' => 'fr']);
});

it('shows only the current community sections on the home page', function () {
    Livewire::test(SectionList::class)->assertSee('English corner')->assertDontSee('Coin francophone');

    session(['community' => 'fr']);
    Livewire::test(SectionList::class)->assertSee('Coin francophone')->assertDontSee('English corner');
});

it('lets guests switch community through the session', function () {
    $this->post(route('community.update'), ['community' => 'fr'])->assertRedirect(route('home'));

    expect(session('community'))->toBe('fr');
});

it('keeps the community in the session only, never on the account', function () {
    $user = userWithRole(Role::MEMBER);
    $user->update(['locale' => 'de']);

    $this->actingAs($user)->post(route('community.update'), ['community' => 'fr']);

    expect(session('community'))->toBe('fr')
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('users', 'community'))->toBeFalse()
        ->and($user->fresh()->locale)->toBe('de');
});

it('does not change the interface language when switching community', function () {
    $user = userWithRole(Role::MEMBER);
    $user->update(['locale' => 'de']);
    session(['community' => 'fr']);

    $this->actingAs($user)->get('/');

    expect(app()->getLocale())->toBe('de')->and(Community::current())->toBe('fr');
});

it('uses the browser language for guests, for both language and community', function () {
    $this->withHeader('Accept-Language', 'pt-BR,pt;q=0.9')->get('/');

    expect(app()->getLocale())->toBe('pt_BR')->and(Community::fromRequest(request()))->toBe('br');
});

it('lets guests pick a language for the session', function () {
    $this->post(route('language.update'), ['locale' => 'it'])->assertRedirect();
    $this->get('/');

    expect(session('locale'))->toBe('it')->and(app()->getLocale())->toBe('it');
});

it('rejects unknown communities', function () {
    $this->post(route('community.update'), ['community' => 'xx'])->assertSessionHasErrors('community');
});

it('keeps every community reachable by url', function () {
    session(['community' => 'en']);

    $this->get(route('sections.show', $this->fr))->assertOk()->assertSee('Coin francophone');
});

it('shows no activity block on the home page, only the sections', function () {
    app(ForumService::class)->createThread(userWithRole(Role::MEMBER), $this->en, 'English thread', 'Hi');

    $this->get('/')->assertOk()->assertSeeLivewire('section-list')->assertDontSee('Mark everything read')->assertDontSee('Latest activity')->assertDontSee('Pick a section on the left to start reading.');
});

it('scopes search to the current community unless all is asked', function () {
    $forum = app(ForumService::class);
    $forum->createThread(userWithRole(Role::MEMBER), $this->en, 'Cheese in English', 'Hi');
    $forum->createThread(userWithRole(Role::MEMBER), $this->fr, 'Cheese en français', 'Salut');

    $titles = fn ($params) => $this->get(route('search', $params))->viewData('results')->pluck('title')->sort()->values()->all();

    expect($titles(['q' => 'cheese']))->toBe(['Cheese in English'])
        ->and($titles(['q' => 'cheese', 'community' => 'fr']))->toBe(['Cheese en français'])
        ->and($titles(['q' => 'cheese', 'community' => 'all']))->toBe(['Cheese en français', 'Cheese in English']);
});

it('picks the browser language for new visitors', function () {
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.8')->get('/');

    expect(Community::fromRequest(request()))->toBe('fr');
});

it('falls back to the default community for unsupported languages', function () {
    $this->withHeader('Accept-Language', 'ja-JP')->get('/');

    expect(Community::fromRequest(request()))->toBe('en');
});

it('maps portuguese browsers to the br community', function () {
    $this->withHeader('Accept-Language', 'pt-PT')->get('/');

    expect(Community::fromRequest(request()))->toBe('br');
});

it('saves the interface language on the account from settings', function () {
    $user = userWithRole(Role::MEMBER);

    $this->actingAs($user)->from(route('settings', 'language'))->post(route('language.update'), ['locale' => 'ru'])->assertRedirect(route('settings', 'language'));
    $this->actingAs($user->fresh())->get(route('settings', 'language'))->assertOk();

    expect($user->fresh()->locale)->toBe('ru')->and(app()->getLocale())->toBe('ru');
    $this->post(route('language.update'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
});

it('gives new accounts the language they were browsing in', function () {
    session(['locale' => 'fr', 'community' => 'es']);

    Livewire::test(AuthModal::class)
        ->set('regName', 'frenchmouse')
        ->set('regEmail', 'fr@example.com')
        ->set('regPassword', 'a-long-password')
        ->set('regPassword_confirmation', 'a-long-password')
        ->call('register');

    expect(User::where('name', 'frenchmouse')->value('locale'))->toBe('fr');
});

it('shows the community flag on sections', function () {
    expect($this->fr->flagUrl())->toEndWith('/img/flags/fr.png')
        ->and($this->en->flagUrl())->toEndWith('/img/flags/gb.png');
});
