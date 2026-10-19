<?php

use App\Livewire\ConversationView;
use App\Livewire\FriendsPage;
use App\Livewire\Inbox;
use App\Livewire\ProfileActions;
use App\Livewire\ThreadView;
use App\Models\Friendship;
use App\Models\Role;
use App\Models\Section;
use App\Services\ForumService;
use App\Services\MessageService;
use Livewire\Livewire;

beforeEach(function () {
    $this->victim = userWithRole(Role::MEMBER);
    $this->harasser = userWithRole(Role::MEMBER);
    Livewire::actingAs($this->victim)->test(ProfileActions::class, ['user' => $this->harasser])->call('toggleBlock');
    $this->victim = $this->victim->fresh();
    $this->harasser = $this->harasser->fresh();
});

it('records the block and lists it on the friends page', function () {
    expect($this->victim->hasBlocked($this->harasser))->toBeTrue();
    Livewire::actingAs($this->victim)->test(FriendsPage::class)->set('tab', 'blocked')->assertSee($this->harasser->name);
});

it('silences every notification from the blocked player', function () {
    $forum = app(ForumService::class);
    $thread = $forum->createThread($this->victim, Section::factory()->create(), 'Mine', 'Hello');
    $forum->reply($this->harasser, $thread, "Hey @{$this->victim->name}\n\n> **{$this->victim->name}** wrote:\n> Hello");

    expect($this->victim->notifications()->count())->toBe(0);
});

it('stops private messages both ways', function () {
    Livewire::actingAs($this->harasser)->test(Inbox::class)
        ->set('recipients', $this->victim->name)->set('subject', 'Hi')->set('body', 'Hi')
        ->call('send')->assertHasErrors('recipients');
    Livewire::actingAs($this->victim)->test(Inbox::class)
        ->set('recipients', $this->harasser->name)->set('subject', 'Hi')->set('body', 'Hi')
        ->call('send')->assertHasErrors('recipients');
});

it('stops the blocked player from writing in a shared conversation', function () {
    $other = userWithRole(Role::MEMBER);
    $conversation = app(MessageService::class)->start($other, collect([$this->victim, $this->harasser]), 'Group', 'Hello all');

    Livewire::actingAs($this->harasser)->test(ConversationView::class, ['conversation' => $conversation])
        ->set('body', 'still here')->call('send')->assertForbidden();
    Livewire::actingAs($other)->test(ConversationView::class, ['conversation' => $conversation])
        ->set('body', 'fine')->call('send')->assertHasNoErrors();
});

it('cannot be added to a conversation by the blocked player', function () {
    $conversation = app(MessageService::class)->start($this->harasser, collect([userWithRole(Role::MEMBER)]), 'Trap', 'Hi');

    Livewire::actingAs($this->harasser)->test(ConversationView::class, ['conversation' => $conversation])
        ->set('inviteName', $this->victim->name)->call('invite')->assertHasErrors('inviteName');
});

it('stops reactions on the victim posts', function () {
    $thread = threadIn(Section::factory()->create(), $this->victim);

    Livewire::actingAs($this->harasser)->test(ThreadView::class, ['thread' => $thread])
        ->call('react', $thread->posts()->first()->id, 'like')->assertForbidden();
});

it('removes friendship and refuses new requests', function () {
    $a = userWithRole(Role::MEMBER);
    $b = userWithRole(Role::MEMBER);
    Friendship::create(['user_id' => $a->id, 'friend_id' => $b->id, 'accepted_at' => now()]);

    Livewire::actingAs($a)->test(ProfileActions::class, ['user' => $b])->call('toggleBlock');
    expect(Friendship::count())->toBe(0);

    Livewire::actingAs($b)->test(ProfileActions::class, ['user' => $a])->call('addFriend')->assertHasErrors('friend');
});

it('collapses the blocked player posts for the victim', function () {
    $thread = threadIn(Section::factory()->create(), $this->harasser);

    Livewire::actingAs($this->victim)->test(ThreadView::class, ['thread' => $thread])->assertSee('Post from a user you ignore or blocked.');
});

it('cannot block staff and can unblock', function () {
    Livewire::actingAs($this->victim)->test(ProfileActions::class, ['user' => userWithRole(Role::MODERATOR, Section::factory()->create())])
        ->call('toggleBlock')->assertHasErrors('block');

    Livewire::actingAs($this->victim)->test(FriendsPage::class)->call('unblock', $this->harasser->id);
    expect($this->victim->fresh()->hasBlocked($this->harasser))->toBeFalse();
});
