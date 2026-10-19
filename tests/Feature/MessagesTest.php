<?php

use App\Livewire\ConversationView;
use App\Livewire\Inbox;
use App\Models\Conversation;
use App\Models\Role;
use App\Services\MessageService;
use Livewire\Livewire;

beforeEach(function () {
    $this->alice = userWithRole(Role::MEMBER);
    $this->bob = userWithRole(Role::MEMBER);
});

it('starts a conversation and notifies recipients', function () {
    Livewire::actingAs($this->alice)
        ->test(Inbox::class)
        ->set('recipients', $this->bob->name)
        ->set('subject', 'Hello')
        ->set('body', 'How are you?')
        ->call('send')
        ->assertRedirect();

    $conversation = Conversation::firstOrFail();
    expect($conversation->hasParticipant($this->bob))->toBeTrue()
        ->and($this->bob->unreadConversationsCount())->toBe(1)
        ->and($this->bob->notifications()->first()->data['kind'])->toBe('message');
});

it('rejects unknown recipients', function () {
    Livewire::actingAs($this->alice)
        ->test(Inbox::class)
        ->set('recipients', 'ghost-player')
        ->set('subject', 'Hello')
        ->set('body', 'Hi')
        ->call('send')
        ->assertHasErrors('recipients');
});

it('keeps conversations private to participants', function () {
    $conversation = app(MessageService::class)->start($this->alice, collect([$this->bob]), 'Secret', 'Just us');
    $outsider = userWithRole(Role::MEMBER);

    $this->actingAs($outsider)->get(route('messages.show', $conversation))->assertForbidden();
    $this->actingAs($this->bob)->get(route('messages.show', $conversation))->assertOk()->assertSee('Just us');
});

it('lets participants reply and leave', function () {
    $conversation = app(MessageService::class)->start($this->alice, collect([$this->bob]), 'Chat', 'Hi Bob');

    Livewire::actingAs($this->bob)
        ->test(ConversationView::class, ['conversation' => $conversation])
        ->set('body', 'Hi Alice')
        ->call('send')
        ->assertSee('Hi Alice');

    Livewire::actingAs($this->bob)
        ->test(ConversationView::class, ['conversation' => $conversation])
        ->call('leave')
        ->assertRedirect(route('messages'));

    expect($conversation->fresh()->hasParticipant($this->bob))->toBeFalse();
});

it('blocks banned users from messaging', function () {
    sanction($this->alice);

    Livewire::actingAs($this->alice)
        ->test(Inbox::class)
        ->set('recipients', $this->bob->name)
        ->set('subject', 'Hello')
        ->set('body', 'Hi')
        ->call('send')
        ->assertForbidden();
});
