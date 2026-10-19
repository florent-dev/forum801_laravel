<?php

use App\Livewire\Admin\ForumSettingsPage;
use App\Livewire\Admin\Health;
use App\Livewire\Admin\PersonalData;
use App\Livewire\Admin\WordFilters;
use App\Livewire\AuthModal;
use App\Livewire\ConversationView;
use App\Livewire\ThreadView;
use App\Models\Report;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Models\WordFilter;
use App\Services\CaptchaService;
use App\Services\ForumService;
use App\Services\MessageService;
use App\Services\PrivacyService;
use App\Services\WordFilterService;
use App\Support\ForumSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(Role::ADMINISTRATOR);
    $this->section = Section::factory()->create();
    $this->forum = app(ForumService::class);
});

function addWord(string $phrase, string $action, bool $whole = true): void
{
    WordFilter::create(['phrase' => $phrase, 'action' => $action, 'whole_word' => $whole]);
    app(WordFilterService::class)->forget();
}

it('keeps the new admin pages for administrators only', function (string $route) {
    $this->get(route($route))->assertRedirect();
    $this->actingAs(userWithRole(Role::SENIOR_MODERATOR))->get(route($route))->assertForbidden();
    $this->actingAs($this->admin)->get(route($route))->assertOk();
})->with(['admin.settings', 'admin.words', 'admin.health', 'admin.privacy']);

it('has sensible defaults: registration and captcha on, no edit window', function () {
    config(['forum.captcha' => true]);

    expect(ForumSettings::registrationOpen())->toBeTrue()
        ->and(app(CaptchaService::class)->enabled())->toBeTrue()
        ->and(ForumSettings::get('edit_window_minutes'))->toBeNull();
});

it('saves the settings from the admin page and logs the change', function () {
    Livewire::actingAs($this->admin)->test(ForumSettingsPage::class)
        ->set('registrationOpen', false)
        ->set('captcha', false)
        ->set('postsPerMinute', 2)
        ->set('maxGuilds', 3)
        ->set('editWindow', '30')
        ->call('save')
        ->assertHasNoErrors();

    expect(ForumSettings::registrationOpen())->toBeFalse()
        ->and(app(CaptchaService::class)->enabled())->toBeFalse()
        ->and(ForumSettings::get('posts_per_minute'))->toBe(2)
        ->and(ForumSettings::get('edit_window_minutes'))->toBe(30)
        ->and(\App\Models\ModerationLog::where('action', 'settings_update')->exists())->toBeTrue();

    Livewire::actingAs($this->admin)->test(ForumSettingsPage::class)->set('postsPerMinute', 0)->call('save')->assertHasErrors('postsPerMinute');
});

it('closes registration everywhere when switched off', function () {
    ForumSettings::save(['registration_open' => false]);

    $this->get('/')->assertDontSee('Register')->assertSee('Registration is currently closed.');
    $this->get(route('register'))->assertForbidden();
    $this->post('/register', ['name' => 'late', 'email' => 'late@test.dev', 'password' => 'long-password-1', 'password_confirmation' => 'long-password-1'])->assertForbidden();
    Livewire::test(AuthModal::class)
        ->set('regName', 'latemouse')->set('regEmail', 'late@test.dev')
        ->set('regPassword', 'long-password-1')->set('regPassword_confirmation', 'long-password-1')
        ->call('register')->assertHasErrors('regName');

    expect(User::where('name', 'latemouse')->exists())->toBeFalse();
});

it('applies the posting rate limit from the settings', function () {
    ForumSettings::save(['posts_per_minute' => 2]);
    $user = userWithRole(Role::MEMBER);
    $thread = $this->forum->createThread($user, $this->section, 'Fast', 'one');
    $this->forum->reply($user, $thread, 'two');

    expect(fn () => $this->forum->reply($user, $thread, 'three'))->toThrow(ValidationException::class);
});

it('applies the maximum post length from the settings', function () {
    ForumSettings::save(['max_post_length' => 100]);
    $user = userWithRole(Role::MEMBER);
    $thread = $this->forum->createThread($user, $this->section, 'Long', 'start');

    Livewire::actingAs($user)->test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', str_repeat('a', 101))->call('reply')->assertHasErrors('replyBody');
});

it('applies the guild limit from the settings', function () {
    $user = userWithRole(Role::MEMBER);
    expect($user->canJoinMoreGuilds())->toBeTrue();

    ForumSettings::save(['max_guilds_per_user' => 0]);
    expect($user->fresh()->canJoinMoreGuilds())->toBeFalse();
});

it('stops authors from editing after the edit window, never moderators', function () {
    $author = userWithRole(Role::MEMBER);
    $thread = $this->forum->createThread($author, $this->section, 'Window', 'first version');
    $post = $thread->posts()->first();
    $moderator = userWithRole(Role::MODERATOR, $this->section);

    expect($author->can('update', $post))->toBeTrue();

    ForumSettings::save(['edit_window_minutes' => 10]);
    $this->travel(11)->minutes();

    expect($author->fresh()->can('update', $post->fresh()))->toBeFalse()
        ->and($moderator->can('update', $post->fresh()))->toBeTrue();

    ForumSettings::save(['edit_window_minutes' => null]);
    expect($author->fresh()->can('update', $post->fresh()))->toBeTrue();
});

it('blocks posts containing a blocked word', function () {
    addWord('badword', 'block');
    $user = userWithRole(Role::MEMBER);
    $thread = $this->forum->createThread($user, $this->section, 'Clean', 'clean');

    Livewire::actingAs($user)->test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', 'this has a BadWord inside')->call('reply')->assertHasErrors('replyBody');
    expect(fn () => $this->forum->createThread($user, $this->section, 'badword title', 'ok'))->toThrow(ValidationException::class);
});

it('masks replaced words, respecting whole words', function () {
    addWord('cheese', 'replace');
    addWord('xyz', 'replace', false);
    $thread = $this->forum->createThread(userWithRole(Role::MEMBER), $this->section, 'I like cheese', 'cheese, cheesecake and abxyzcd');

    expect($thread->title)->toBe('I like ******')
        ->and($thread->posts()->first()->body)->toBe('******, cheesecake and ab***cd');
});

it('publishes reviewed words and reports them to moderators automatically', function () {
    addWord('suspicious', 'review');
    $thread = $this->forum->createThread(userWithRole(Role::MEMBER), $this->section, 'Hello', 'a suspicious offer');
    $post = $thread->posts()->first();

    $report = Report::where('post_id', $post->id)->first();
    expect($post->body)->toBe('a suspicious offer')
        ->and($report)->not->toBeNull()
        ->and($report->reporter_id)->toBeNull()
        ->and($report->reason)->toContain('suspicious');

    $this->actingAs(userWithRole(Role::SENIOR_MODERATOR))->get(route('moderation'))->assertOk()->assertSee('automatic word filter');
});

it('filters private messages too', function () {
    addWord('cheese', 'replace');
    addWord('forbidden', 'block');
    $a = userWithRole(Role::MEMBER);
    $b = userWithRole(Role::MEMBER);
    $conversation = app(MessageService::class)->start($a, collect([$b]), 'cheese talk', 'more cheese');

    expect($conversation->subject)->toBe('****** talk')
        ->and($conversation->messages()->first()->body)->toBe('more ******');
    expect(fn () => app(MessageService::class)->reply($a, $conversation, 'forbidden'))->toThrow(ValidationException::class);
});

it('manages and tests the word list from the admin page', function () {
    Livewire::actingAs($this->admin)->test(WordFilters::class)
        ->set('phrase', 'spam')->set('action', 'block')->call('add')->assertHasNoErrors()
        ->set('phrase', 'spam')->call('add')->assertHasErrors('phrase')
        ->set('sample', 'buy SPAM now')->call('test')
        ->assertSee('forbidden word');

    $filter = WordFilter::firstWhere('phrase', 'spam');
    Livewire::actingAs($this->admin)->test(WordFilters::class)
        ->call('setAction', $filter->id, 'replace')
        ->set('sample', 'buy spam now')->call('test')->assertSee('buy **** now')
        ->call('remove', $filter->id);

    expect(WordFilter::count())->toBe(0);
});

it('shows system health and sends a test email', function () {
    Mail::fake();

    Livewire::actingAs($this->admin)->test(Health::class)
        ->assertSee('PostgreSQL')->assertSee('Scheduler')->assertSee('Largest tables')
        ->set('mailTo', 'ops@test.dev')->call('sendTestMail')->assertHasNoErrors();
});

it('records the scheduler heartbeat', function () {
    $this->artisan('schedule:run')->assertSuccessful();

    expect(cache(\App\Services\HealthService::HEARTBEAT))->not->toBeNull();
});

it('lets users download their own data', function () {
    $user = userWithRole(Role::MEMBER);
    $this->forum->createThread($user, $this->section, 'Mine', 'my words');

    $response = $this->actingAs($user)->get(route('settings.export'))->assertOk()->assertHeader('Content-Type', 'application/json; charset=utf-8');
    $data = json_decode($response->getContent(), true);

    expect($data['account']['email'])->toBe($user->email)
        ->and($data['account'])->not->toHaveKey('password')
        ->and($data['posts'][0]['body'])->toBe('my words');
    $this->get(route('settings.export'))->assertOk();
});

it('lets administrators export and anonymize an account', function () {
    $user = userWithRole(Role::MEMBER);
    $thread = $this->forum->createThread($user, $this->section, 'Kept', 'still readable');

    $this->actingAs($this->admin)->get(route('admin.privacy.export', $user))->assertOk()->assertSee($user->email, false);

    Livewire::actingAs($this->admin)->test(PersonalData::class)
        ->set('search', $user->name)->assertSee($user->email)
        ->call('pick', $user->id)
        ->set('confirmName', 'wrong')->call('anonymize')->assertHasErrors('confirmName')
        ->set('confirmName', $user->name)->call('anonymize')->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('deleted-'.$user->id)
        ->and($user->email)->not->toContain('@test')
        ->and($user->status)->toBe('deleted')
        ->and($user->roles()->count())->toBe(0)
        ->and($thread->posts()->first()->body)->toBe('still readable');
});

it('refuses to anonymize administrators', function () {
    $other = userWithRole(Role::ADMINISTRATOR);

    Livewire::actingAs($this->admin)->test(PersonalData::class)
        ->call('pick', $other->id)->set('confirmName', $other->name)->call('anonymize')->assertForbidden();
});

it('hands guild ownership over when the owner is anonymized', function () {
    $owner = userWithRole(Role::MEMBER);
    $member = userWithRole(Role::MEMBER);
    $guild = app(\App\Services\GuildService::class)->create($owner, ['name' => 'Cheese Knights', 'tag' => 'chk', 'community' => 'en', 'recruitment' => 'open']);
    app(\App\Services\GuildService::class)->join($member, $guild);

    app(PrivacyService::class)->anonymize($this->admin, $owner);

    expect($guild->members()->where('user_id', $member->id)->value('role'))->toBe('owner');
});
