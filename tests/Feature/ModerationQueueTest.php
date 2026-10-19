<?php

use App\Livewire\ModerationQueue;
use App\Livewire\ReportList;
use App\Livewire\ReportPanel;
use App\Livewire\ThreadView;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->post = threadIn($this->section)->posts()->first();
    $this->report = Report::create(['post_id' => $this->post->id, 'reporter_id' => userWithRole(Role::MEMBER)->id, 'reason' => 'Very rude']);
    $this->admin = userWithRole(Role::ADMINISTRATOR);
});

it('lists reports in the sidebar and filters them by status', function () {
    $this->report->update(['status' => 'resolved']);

    Livewire::actingAs($this->admin)->test(ReportList::class)->assertDontSee('Very rude')
        ->set('status', 'resolved')->assertSee('Very rude')
        ->set('status', 'all')->assertSee('Very rude')
        ->set('status', 'bogus')->assertSet('status', 'open');
});

it('opens the reported post on the right page of its thread', function () {
    Post::factory()->count(Post::PER_PAGE + 1)->create(['thread_id' => $this->post->thread_id]);
    $late = Post::where('thread_id', $this->post->thread_id)->latest('id')->first();
    $report = Report::create(['post_id' => $late->id, 'reporter_id' => userWithRole(Role::MEMBER)->id, 'reason' => 'Late one']);

    $this->actingAs($this->admin)->get(route('moderation.reports.show', $report))
        ->assertRedirect(route('moderation.reports.show', ['report' => $report->id, 'page' => 2]));
    $this->actingAs($this->admin)->get(route('moderation.reports.show', ['report' => $report->id, 'page' => 2]))
        ->assertOk()->assertSee('Late one')->assertSeeHtml('reported-post');
});

it('dismisses a report and moves to the next one', function () {
    $next = Report::create(['post_id' => threadIn($this->section)->posts()->first()->id, 'reporter_id' => userWithRole(Role::MEMBER)->id, 'reason' => 'Next one']);

    Livewire::actingAs($this->admin)->test(ReportPanel::class, ['report' => $this->report])
        ->call('resolve', 'dismissed')
        ->assertRedirectContains(route('moderation.reports.show', $next));

    expect($this->report->fresh()->status)->toBe('dismissed')
        ->and(ModerationLog::where('action', 'report.dismissed')->exists())->toBeTrue();
});

it('goes back to the moderation page when no report is left', function () {
    Livewire::actingAs($this->admin)->test(ReportPanel::class, ['report' => $this->report])
        ->call('resolve', 'resolved')
        ->assertRedirect(route('moderation'));
});

it('rejects unknown report statuses', function () {
    Livewire::actingAs($this->admin)->test(ReportPanel::class, ['report' => $this->report])->call('resolve', 'deleted')->assertStatus(422);
});

it('hides the post and resolves in one click', function () {
    Livewire::actingAs($this->admin)->test(ReportPanel::class, ['report' => $this->report])->call('hideAndResolve');

    expect($this->post->fresh()->is_hidden)->toBeTrue()->and($this->report->fresh()->status)->toBe('resolved');
});

it('locks the reported thread from the report bar', function () {
    Livewire::actingAs($this->admin)->test(ReportPanel::class, ['report' => $this->report])->call('toggleLock');

    expect($this->post->thread->fresh()->is_locked)->toBeTrue();
});

it('shows a deleted reported post to the reviewer', function () {
    $this->post->update(['body_html' => '<p>was deleted</p>']);
    $this->post->delete();

    Livewire::actingAs($this->admin)->test(ThreadView::class, ['thread' => $this->post->thread, 'highlightPostId' => $this->post->id])
        ->assertSet('showDeleted', true)
        ->assertSee('was deleted');
});

it('shows logs of moderated sections only', function () {
    $otherSection = Section::factory()->create();
    ModerationLog::record($this->admin, 'thread.lock', threadIn($this->section), $this->section->id, ['title' => 'visible-log']);
    ModerationLog::record($this->admin, 'thread.lock', threadIn($otherSection), $otherSection->id, ['title' => 'hidden-log']);
    ModerationLog::record($this->admin, 'user.banned', User::factory()->create(), null, ['name' => 'global-log']);

    Livewire::actingAs(userWithRole(Role::MODERATOR, $this->section))->test(ModerationQueue::class)
        ->call('setTab', 'logs')
        ->assertSee('visible-log')->assertDontSee('hidden-log')->assertDontSee('global-log');

    Livewire::actingAs($this->admin)->test(ModerationQueue::class)
        ->call('setTab', 'logs')
        ->assertSee('visible-log')->assertSee('hidden-log')->assertSee('global-log');
});

it('blocks members and banned moderators', function () {
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ModerationQueue::class)->assertForbidden();
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ReportList::class)->assertForbidden();
    $this->actingAs(userWithRole(Role::MEMBER))->get(route('moderation.reports.show', $this->report))->assertForbidden();

    $banned = userWithRole(Role::SENIOR_MODERATOR);
    sanction($banned);
    $this->actingAs($banned)->get(route('moderation'))->assertForbidden();
});
