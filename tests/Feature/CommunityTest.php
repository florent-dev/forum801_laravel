<?php

use App\Livewire\AuthModal;
use App\Livewire\ThreadList;
use App\Livewire\ThreadView;
use App\Models\Post;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\ForumService;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->forum = app(ForumService::class);
});

it('notifies thread subscribers about replies', function () {
    $author = userWithRole(Role::MEMBER);
    $replier = userWithRole(Role::MEMBER);
    $thread = $this->forum->createThread($author, $this->section, 'Hello there', 'First post');

    $this->forum->reply($replier, $thread, 'A reply');

    expect($author->notifications()->count())->toBe(1)
        ->and($author->notifications()->first()->data['kind'])->toBe('reply')
        ->and($replier->notifications()->count())->toBe(0)
        ->and($replier->fresh()->isSubscribedTo($thread))->toBeTrue();
});

it('notifies and links mentioned users once', function () {
    $author = userWithRole(Role::MEMBER);
    $friend = User::factory()->create(['name' => 'friendly']);
    $thread = $this->forum->createThread($author, $this->section, 'Mention test', 'Hi @friendly and @friendly, @nobody');

    expect($friend->notifications()->count())->toBe(1)
        ->and($friend->notifications()->first()->data['kind'])->toBe('mention')
        ->and($thread->posts()->first()->body_html)->toContain('href="'.route('profile', 'friendly').'"')
        ->and($thread->posts()->first()->body_html)->not->toContain('/u/nobody');
});

it('does not notify mentioned users who cannot see the section', function () {
    $private = Section::factory()->private()->create();
    $mod = userWithRole(Role::PRIVATE_MODERATOR);
    $outsider = User::factory()->create(['name' => 'outsider']);

    $this->forum->createThread($mod, $private, 'Secret', 'Hey @outsider');

    expect($outsider->notifications()->count())->toBe(0);
});

it('notifies quoted users', function () {
    $quoted = userWithRole(Role::MEMBER);
    $thread = $this->forum->createThread($quoted, $this->section, 'Quote me', 'Original words');
    $this->forum->unsubscribe($quoted, $thread);

    $this->forum->reply(userWithRole(Role::MEMBER), $thread, "> **{$quoted->name}** wrote:\n> Original words\n\nI agree");

    expect($quoted->notifications()->first()->data['kind'])->toBe('quote');
});

it('lets users follow and unfollow a thread', function () {
    $user = userWithRole(Role::MEMBER);
    $thread = threadIn($this->section);

    Livewire::actingAs($user)->test(ThreadView::class, ['thread' => $thread])->call('toggleSubscription');
    expect($user->isSubscribedTo($thread))->toBeTrue();

    Livewire::actingAs($user)->test(ThreadView::class, ['thread' => $thread])->call('toggleSubscription');
    expect($user->isSubscribedTo($thread))->toBeFalse();
});

it('redirects a post link to the right page', function () {
    $thread = threadIn($this->section);
    Post::factory()->count(Post::PER_PAGE + 2)->create(['thread_id' => $thread->id]);
    $last = Post::where('thread_id', $thread->id)->latest('id')->first();

    $this->get(route('posts.show', $last))->assertRedirect(route('threads.show', ['thread' => $thread->id, 'slug' => $thread->slug, 'page' => 2, 'p' => $last->id]));
});

it('does not reveal hidden posts through post links', function () {
    $post = threadIn($this->section)->posts()->first();
    $post->update(['is_hidden' => true]);

    $this->get(route('posts.show', $post))->assertForbidden();
    $this->actingAs(userWithRole(Role::MEMBER))->get(route('posts.show', $post))->assertForbidden();
});

it('jumps to the first unread post', function () {
    $user = userWithRole(Role::MEMBER);
    $thread = threadIn($this->section);
    $first = $thread->posts()->first();
    $this->forum->markRead($user, $thread, $first->id);
    $next = Post::factory()->create(['thread_id' => $thread->id]);

    $this->actingAs($user)->get(route('threads.unread', $thread))->assertRedirect(route('threads.show', ['thread' => $thread->id, 'slug' => $thread->slug, 'page' => 1, 'p' => $next->id]));
});

it('counts thread views once per session', function () {
    $thread = threadIn($this->section);

    $this->get($thread->url());
    $this->get($thread->url());

    expect($thread->fresh()->views_count)->toBe(1);
});

it('marks a whole section as read', function () {
    $user = userWithRole(Role::MEMBER);
    $threads = collect([threadIn($this->section), threadIn($this->section)]);

    Livewire::actingAs($user)->test(ThreadList::class, ['section' => $this->section])->call('markSectionRead');

    expect(\DB::table('thread_reads')->where('user_id', $user->id)->count())->toBe(2);
});

it('sorts threads by replies', function () {
    $quiet = threadIn($this->section);
    $busy = threadIn($this->section);
    $busy->update(['posts_count' => 50]);

    Livewire::test(ThreadList::class, ['section' => $this->section])
        ->set('sort', 'replies')
        ->assertSeeInOrder([$busy->title, $quiet->title]);
});

it('logs in and registers through the modal', function () {
    User::factory()->create(['name' => 'modaluser', 'password' => 'secret-password']);

    Livewire::test(AuthModal::class)
        ->set('name', 'modaluser')
        ->set('password', 'wrong')
        ->call('login')
        ->assertHasErrors('name');
    $this->assertGuest();

    Livewire::test(AuthModal::class)
        ->set('name', 'modaluser')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect();
    $this->assertAuthenticated();
});

it('registers a new member through the modal', function () {
    Livewire::test(AuthModal::class)
        ->set('regName', 'newcomer')
        ->set('regEmail', 'new@example.com')
        ->set('regPassword', 'a-long-password')
        ->set('regPassword_confirmation', 'a-long-password')
        ->call('register')
        ->assertRedirect();

    expect(User::where('name', 'newcomer')->first()->hasRole(Role::MEMBER))->toBeTrue();
});

it('changes the password from settings', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $this->actingAs($user)->put(route('settings.password'), [
        'current_password' => 'old-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('settings', 'account'));

    expect(\Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

it('keeps notification redirects on site', function () {
    $user = userWithRole(Role::MEMBER);
    foreach (['https://evil.example.com', url('/').'.evil.com/x', 'javascript:alert(1)'] as $bad) {
        $user->notifications()->delete();
        $user->notify(new \App\Notifications\ForumActivity('reply', 'x', [], $bad));
        $id = $user->notifications()->first()->id;
        $this->actingAs($user)->get(route('notifications.open', $id))->assertRedirect(route('home'));
    }
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('filters search results by author and section', function () {
    $alice = User::factory()->create(['name' => 'searchalice']);
    $other = Section::factory()->create();
    $this->forum->createThread($alice, $this->section, 'Cheese recipes', 'Brie');
    $this->forum->createThread(userWithRole(Role::MEMBER), $this->section, 'More cheese', 'Gouda');
    $this->forum->createThread($alice, $other, 'Cheese elsewhere', 'Feta');

    $titles = $this->get(route('search', ['author' => 'searchalice', 'section' => $this->section->id]))
        ->assertOk()
        ->viewData('results')->pluck('title')->all();
    expect($titles)->toBe(['Cheese recipes']);

    $posts = $this->get(route('search', ['q' => 'gouda', 'in' => 'posts']))->viewData('results');
    expect($posts->pluck('thread.title')->all())->toBe(['More cheese']);
});

it('never shows private threads in search', function () {
    $private = Section::factory()->private()->create();
    $this->forum->createThread(userWithRole(Role::ADMINISTRATOR), $private, 'Secret staff plan', 'Hidden');

    expect($this->get(route('search', ['q' => 'secret']))->viewData('results')->total())->toBe(0);
});

it('filters search by period, prefix, solved and poll, and sorts', function () {
    $author = userWithRole(Role::MEMBER);
    $old = $this->forum->createThread($author, $this->section, 'Cheese old', 'x x', 'guide');
    $old->forceFill(['last_post_at' => now()->subMonths(2)])->save();
    $solved = $this->forum->createThread($author, $this->section, 'Cheese solved', 'x x', 'question');
    $solved->forceFill(['solution_post_id' => $solved->posts()->first()->id])->save();
    $this->forum->createThread($author, $this->section, 'Cheese poll', 'x x', null, ['question' => 'Q?', 'options' => ['a', 'b']]);

    $titles = fn (array $params) => $this->get(route('search', $params))->viewData('results')->pluck('title')->sort()->values()->all();

    expect($titles(['q' => 'cheese', 'period' => 'week']))->toBe(['Cheese poll', 'Cheese solved'])
        ->and($titles(['q' => 'cheese', 'prefix' => 'guide']))->toBe(['Cheese old'])
        ->and($titles(['solved' => 1]))->toBe(['Cheese solved'])
        ->and($titles(['poll' => 1]))->toBe(['Cheese poll']);
    expect($this->get(route('search', ['q' => 'cheese', 'sort' => 'oldest']))->viewData('results')->first()->title)->toBe('Cheese old');
    $this->get(route('search', ['sort' => 'bogus']))->assertSessionHasErrors('sort');
});

it('searches titles and contents together', function () {
    $this->forum->createThread(userWithRole(Role::MEMBER), $this->section, 'Plain title', 'mozzarella inside');
    $this->forum->createThread(userWithRole(Role::MEMBER), $this->section, 'Mozzarella title', 'nothing');

    $titles = $this->get(route('search', ['q' => 'mozzarella', 'in' => 'all']))->viewData('results')->pluck('title')->sort()->values()->all();

    expect($titles)->toBe(['Mozzarella title', 'Plain title']);
});
