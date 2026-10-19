<?php

use App\Livewire\FriendsPage;
use App\Livewire\Inbox;
use App\Livewire\ProfileActions;
use App\Livewire\ThreadView;
use App\Models\Friendship;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\ForumService;
use Livewire\Livewire;

beforeEach(function () {
    $this->alice = userWithRole(Role::MEMBER);
    $this->bob = userWithRole(Role::MEMBER);
});

it('sends, accepts and removes friend requests', function () {
    Livewire::actingAs($this->alice)->test(ProfileActions::class, ['user' => $this->bob])->call('addFriend');
    expect(Friendship::whereNull('accepted_at')->count())->toBe(1)
        ->and($this->bob->notifications()->first()->data['kind'])->toBe('friend');

    $request = Friendship::first();
    Livewire::actingAs($this->bob)->test(FriendsPage::class)->call('accept', $request->id);
    expect($this->alice->fresh()->isFriendOf($this->bob))->toBeTrue()
        ->and($this->bob->fresh()->isFriendOf($this->alice))->toBeTrue();

    Livewire::actingAs($this->alice)->test(ProfileActions::class, ['user' => $this->bob])->call('removeFriend');
    expect(Friendship::count())->toBe(0);
});

it('accepts automatically when both players ask each other', function () {
    Livewire::actingAs($this->alice)->test(ProfileActions::class, ['user' => $this->bob])->call('addFriend');
    Livewire::actingAs($this->bob)->test(ProfileActions::class, ['user' => $this->alice])->call('addFriend');

    expect(Friendship::whereNotNull('accepted_at')->count())->toBe(1);
});

it('cannot accept a request addressed to someone else', function () {
    Livewire::actingAs($this->alice)->test(ProfileActions::class, ['user' => $this->bob])->call('addFriend');

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(FriendsPage::class)->call('accept', Friendship::first()->id);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('adds friends by nickname and validates it', function () {
    Livewire::actingAs($this->alice)->test(FriendsPage::class)->set('addName', $this->bob->name)->call('add')->assertHasNoErrors();
    Livewire::actingAs($this->alice)->test(FriendsPage::class)->set('addName', 'ghost-player')->call('add')->assertHasErrors('addName');
    Livewire::actingAs($this->alice)->test(FriendsPage::class)->set('addName', $this->alice->name)->call('add')->assertHasErrors('addName');
});

it('ignores a player: collapsed posts, no messages, no friend requests', function () {
    Livewire::actingAs($this->alice)->test(ProfileActions::class, ['user' => $this->bob])->call('toggleIgnore');
    expect($this->alice->fresh()->ignores($this->bob))->toBeTrue();

    $thread = threadIn(Section::factory()->create(), $this->bob);
    Livewire::actingAs($this->alice->fresh())->test(ThreadView::class, ['thread' => $thread])->assertSee('Post from a user you ignore or blocked.');

    Livewire::actingAs($this->bob)->test(Inbox::class)
        ->set('recipients', $this->alice->name)->set('subject', 'Hi')->set('body', 'Hello')
        ->call('send')->assertHasErrors('recipients');

    Livewire::actingAs($this->bob)->test(ProfileActions::class, ['user' => $this->alice])->call('addFriend')->assertHasErrors('friend');
});

it('cannot ignore staff members', function () {
    $mod = userWithRole(Role::SENIOR_MODERATOR);

    Livewire::actingAs($this->alice)->test(ProfileActions::class, ['user' => $mod])->call('toggleIgnore')->assertHasErrors('ignore');
});

it('hides the online status when asked', function () {
    $this->bob->update(['last_seen_at' => now(), 'hide_online' => true]);

    $this->actingAs($this->alice);
    expect($this->bob->fresh()->isOnline())->toBeFalse()
        ->and(User::visiblyOnline()->pluck('id')->all())->not->toContain($this->bob->id);
    $this->actingAs($this->bob);
    expect($this->bob->fresh()->isOnline())->toBeTrue();
});

it('respects the mention preference', function (string $policy, bool $friends, bool $notified) {
    $this->bob->update(['mention_policy' => $policy]);
    if ($friends) {
        Friendship::create(['user_id' => $this->alice->id, 'friend_id' => $this->bob->id, 'accepted_at' => now()]);
    }

    app(ForumService::class)->createThread($this->alice, Section::factory()->create(), 'Ping', 'Hey @'.$this->bob->name);

    expect($this->bob->notifications()->where('data->kind', 'mention')->exists())->toBe($notified);
})->with([
    'everyone' => ['everyone', false, true],
    'friends, not friend' => ['friends', false, false],
    'friends, friend' => ['friends', true, true],
    'nobody, friend' => ['nobody', true, false],
]);

it('does not notify mentions from ignored players', function () {
    $this->bob->ignoredUsers()->attach($this->alice->id, ['created_at' => now()]);

    app(ForumService::class)->createThread($this->alice, Section::factory()->create(), 'Ping', 'Hey @'.$this->bob->name);

    expect($this->bob->notifications()->count())->toBe(0);
});

it('saves privacy settings', function () {
    $this->actingAs($this->alice)->put(route('settings.privacy'), ['hide_online' => '1', 'mention_policy' => 'friends'])
        ->assertRedirect(route('settings', 'privacy'));

    expect($this->alice->fresh()->hide_online)->toBeTrue()->and($this->alice->fresh()->mention_policy)->toBe('friends');
    $this->actingAs($this->alice)->put(route('settings.privacy'), ['mention_policy' => 'hackers'])->assertSessionHasErrors('mention_policy');
});
