<?php

use App\Livewire\NewThread;
use App\Livewire\ThreadView;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Role;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumService;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->author = userWithRole(Role::MEMBER);
});

function makePoll(User $author, Section $section, array $options = []): Poll
{
    $thread = app(ForumService::class)->createThread($author, $section, 'Poll thread', 'Vote!', 'question', $options + [
        'question' => 'Best cheese?', 'options' => ['Brie', 'Gouda', 'Feta'],
    ]);

    return Poll::where('thread_id', $thread->id)->firstOrFail();
}

function voteAs(User $user, Poll $poll, array $optionIds)
{
    return Livewire::actingAs($user)->test(ThreadView::class, ['thread' => $poll->thread])
        ->set('ballot.p'.$poll->id, $poll->multiple ? array_map('strval', $optionIds) : (count($optionIds) === 1 ? (string) $optionIds[0] : array_map('strval', $optionIds)))
        ->call('vote', $poll->id);
}

it('creates a thread with a fully configured poll', function () {
    Livewire::actingAs($this->author)->test(NewThread::class, ['section' => $this->section])
        ->set('title', 'Which map?')->set('body', 'Tell me')->set('prefix', 'question')
        ->set('withPoll', true)->set('pollQuestion', 'Pick')
        ->set('pollOptions', ['A', '', 'B', 'C'])->set('pollMultiple', true)->set('pollMax', '2')
        ->set('pollEnd', '1w')->set('pollResults', 'after_vote')->set('pollShowVoters', true)->set('pollAllowChange', false)
        ->call('save')->assertHasNoErrors();

    $poll = Thread::where('title', 'Which map?')->first()->posts()->first()->poll;
    expect($poll->options->pluck('label')->all())->toBe(['A', 'B', 'C'])
        ->and($poll->max_choices)->toBe(2)
        ->and($poll->closes_at->between(now()->addDays(6), now()->addDays(8)))->toBeTrue()
        ->and($poll->results_visibility)->toBe('after_vote')
        ->and($poll->show_voters)->toBeTrue()
        ->and($poll->allow_change)->toBeFalse();
});

it('attaches a poll to a reply', function () {
    $thread = threadIn($this->section);

    Livewire::actingAs($this->author)->test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', 'Let us vote')->set('withPoll', true)->set('pollQuestion', 'Now?')
        ->set('pollOptions', ['Yes', 'No'])->set('pollEnd', 'custom')->set('pollEndAt', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('reply')->assertHasNoErrors()->assertSee('Now?');

    $poll = Poll::first();
    expect($poll->post->body)->toBe('Let us vote')->and($poll->thread_id)->toBe($thread->id)
        ->and($poll->closes_at->isFuture())->toBeTrue();
});

it('validates the poll composer', function (array $set, string $error) {
    $component = Livewire::actingAs($this->author)->test(NewThread::class, ['section' => $this->section])
        ->set('title', 'Poll')->set('body', 'x x')->set('withPoll', true)->set('pollQuestion', 'Q?')->set('pollOptions', ['a', 'b']);
    foreach ($set as $key => $value) {
        $component->set($key, $value);
    }
    $component->call('save')->assertHasErrors($error);
})->with([
    'one answer' => [['pollOptions' => ['only', '']], 'pollOptions'],
    'duplicates' => [['pollOptions' => ['same', 'same']], 'pollOptions.*'],
    'past date' => [['pollEnd' => 'custom', 'pollEndAt' => '2000-01-01T10:00'], 'pollEndAt'],
    'missing date' => [['pollEnd' => 'custom'], 'pollEndAt'],
    'bad visibility' => [['pollResults' => 'never'], 'pollResults'],
    'max too high' => [['pollMultiple' => true, 'pollMax' => '9'], 'pollMax'],
]);

it('votes, changes and retracts a single choice vote', function () {
    $poll = makePoll($this->author, $this->section);
    [$brie, $gouda] = $poll->options;
    $voter = userWithRole(Role::MEMBER);

    voteAs($voter, $poll, [$brie->id]);
    voteAs($voter, $poll, [$gouda->id]);
    expect(PollVote::where('user_id', $voter->id)->pluck('poll_option_id')->all())->toBe([$gouda->id]);

    Livewire::actingAs($voter)->test(ThreadView::class, ['thread' => $poll->thread])->call('retractVote', $poll->id);
    expect(PollVote::count())->toBe(0);
});

it('respects multiple choice and the maximum of answers', function () {
    $single = makePoll($this->author, $this->section);
    $multi = makePoll(userWithRole(Role::MEMBER), Section::factory()->create(), ['multiple' => true, 'max_choices' => 2]);
    $ids = $multi->options->pluck('id')->all();
    $voter = userWithRole(Role::MEMBER);

    voteAs($voter, $multi, $ids)->assertHasErrors('pollChoices');
    voteAs($voter, $multi, array_slice($ids, 0, 2))->assertHasNoErrors();
    // The server refuses two answers on a single choice poll, whatever the client sends
    expect(fn () => app(\App\Services\CommunityToolsService::class)->vote($voter, $single, $single->options->take(2)->pluck('id')->all()))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    // Options of another poll are ignored
    voteAs($voter, $single, [$ids[0]])->assertHasErrors('pollChoices');
});

it('locks votes when changes are not allowed', function () {
    $poll = makePoll($this->author, $this->section, ['allow_change' => false]);
    $voter = userWithRole(Role::MEMBER);

    voteAs($voter, $poll, [$poll->options[0]->id])->assertHasNoErrors();
    voteAs($voter, $poll, [$poll->options[1]->id])->assertHasErrors('pollChoices');
    Livewire::actingAs($voter)->test(ThreadView::class, ['thread' => $poll->thread])->call('retractVote', $poll->id)->assertForbidden();
});

it('hides results until voting or until the end', function () {
    $afterVote = makePoll($this->author, $this->section, ['results' => 'after_vote']);
    $afterClose = makePoll(userWithRole(Role::MEMBER), Section::factory()->create(), ['results' => 'after_close']);

    expect($afterVote->resultsVisibleFor(false))->toBeFalse()->and($afterVote->resultsVisibleFor(true))->toBeTrue()
        ->and($afterClose->resultsVisibleFor(true))->toBeFalse();
    $afterClose->update(['closes_at' => now()->subMinute()]);
    expect($afterClose->fresh()->resultsVisibleFor(false))->toBeTrue();

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $afterVote->thread])->assertSee('Results are shown once you voted.');
});

it('shows voter names only on public polls', function () {
    $public = makePoll($this->author, $this->section, ['show_voters' => true]);
    $voter = User::factory()->create(['name' => 'publicvoter', 'email_verified_at' => now()]);
    voteAs($voter, $public, [$public->options[0]->id]);

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $public->thread])->assertSee('publicvoter');

    $secret = makePoll(userWithRole(Role::MEMBER), Section::factory()->create());
    $hidden = User::factory()->create(['name' => 'secretvoter', 'email_verified_at' => now()]);
    voteAs($hidden, $secret, [$secret->options[0]->id]);
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $secret->thread])->assertDontSee('secretvoter');
});

it('refuses votes on ended polls, locked threads and from muted members', function () {
    $poll = makePoll($this->author, $this->section);
    $option = $poll->options->first()->id;

    $poll->update(['closes_at' => now()->subMinute()]);
    voteAs(userWithRole(Role::MEMBER), $poll->fresh(), [$option])->assertForbidden();

    $poll->update(['closes_at' => null]);
    $poll->thread->update(['is_locked' => true]);
    voteAs(userWithRole(Role::MEMBER), $poll->fresh(), [$option])->assertForbidden();

    $poll->thread->update(['is_locked' => false]);
    voteAs(sanction(userWithRole(Role::MEMBER), 'mute'), $poll->fresh(), [$option])->assertForbidden();
});

it('lets the post author or a moderator end the poll', function () {
    $poll = makePoll($this->author, $this->section);

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $poll->thread])->call('closePoll', $poll->id)->assertForbidden();
    Livewire::actingAs($this->author)->test(ThreadView::class, ['thread' => $poll->thread])->call('closePoll', $poll->id);
    expect($poll->fresh()->isClosed())->toBeTrue();

    $other = makePoll(userWithRole(Role::MEMBER), $this->section);
    Livewire::actingAs(userWithRole(Role::MODERATOR, $this->section))->test(ThreadView::class, ['thread' => $other->thread])->call('closePoll', $other->id);
    expect($other->fresh()->isClosed())->toBeTrue();
});

it('cannot reach polls of other threads', function () {
    $poll = makePoll($this->author, $this->section);
    $elsewhere = threadIn($this->section);

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $elsewhere])
        ->set('ballot.p'.$poll->id, (string) $poll->options[0]->id)->call('vote', $poll->id);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('finds threads with polls in search', function () {
    makePoll($this->author, $this->section);

    expect($this->get(route('search', ['poll' => 1, 'community' => 'all']))->viewData('results')->total())->toBe(1);
});

it('prepares an empty list for multiple choice ballots so checkboxes stay independent', function () {
    $poll = makePoll($this->author, $this->section, ['multiple' => true]);
    $single = makePoll(userWithRole(Role::MEMBER), $this->section);

    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $poll->thread])
        ->assertSet('ballot.p'.$poll->id, [])
        ->assertSeeHtml('wire:model="ballot.p'.$poll->id.'"');
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $single->thread])
        ->assertSet('ballot.p'.$single->id, '');
});

it('restores previous answers after a reload', function () {
    $poll = makePoll($this->author, $this->section, ['multiple' => true]);
    $voter = userWithRole(Role::MEMBER);
    voteAs($voter, $poll, [$poll->options[0]->id, $poll->options[2]->id]);

    Livewire::actingAs($voter)->test(ThreadView::class, ['thread' => $poll->thread])
        ->assertSet('ballot.p'.$poll->id, [(string) $poll->options[0]->id, (string) $poll->options[2]->id]);
});
