<?php

use App\Livewire\ReportList;
use App\Livewire\ReportPanel;
use App\Livewire\ThreadView;
use App\Models\ModerationLog;
use App\Models\Report;
use App\Models\Role;
use App\Models\Section;
use Livewire\Livewire;

beforeEach(function () {
    $this->assigned = Section::factory()->create();
    $this->other = Section::factory()->create();
    $this->mod = userWithRole(Role::MODERATOR, $this->assigned);
});

it('can lock, pin and hide in its assigned section and logs it', function () {
    $thread = threadIn($this->assigned);
    $post = $thread->posts()->first();

    Livewire::actingAs($this->mod)
        ->test(ThreadView::class, ['thread' => $thread])
        ->call('toggleLock')
        ->call('togglePin')
        ->call('toggleHidden', $post->id)
        ->assertDispatched('open-modal')
        ->set('reason', 'Spam')
        ->call('confirmReason')
        ->assertHasNoErrors();

    $thread->refresh();
    expect($thread->is_locked)->toBeTrue()
        ->and($thread->is_pinned)->toBeTrue()
        ->and($post->fresh()->is_hidden)->toBeTrue()
        ->and(ModerationLog::pluck('action')->all())->toEqualCanonicalizing(['thread.lock', 'thread.pin', 'post.hide'])
        ->and($post->user->notifications()->first()->data['text'])->toContain('Spam');
});

it('cannot moderate other sections', function () {
    $thread = threadIn($this->other);

    Livewire::actingAs($this->mod)
        ->test(ThreadView::class, ['thread' => $thread])
        ->call('toggleLock')
        ->assertForbidden();
});

it('can only move threads to sections it moderates', function () {
    $thread = threadIn($this->assigned);

    Livewire::actingAs($this->mod)
        ->test(ThreadView::class, ['thread' => $thread])
        ->set('moveTarget', $this->other->id)
        ->call('move')
        ->assertForbidden();
    expect($thread->fresh()->section_id)->toBe($this->assigned->id);

    $this->mod->assignRole(Role::MODERATOR, $this->other);
    Livewire::actingAs($this->mod->fresh())
        ->test(ThreadView::class, ['thread' => $thread])
        ->set('moveTarget', $this->other->id)
        ->call('move');
    expect($thread->fresh()->section_id)->toBe($this->other->id)
        ->and(ModerationLog::where('action', 'thread.move')->exists())->toBeTrue();
});

it('can reply to a locked thread in its section', function () {
    $thread = threadIn($this->assigned);
    $thread->update(['is_locked' => true]);

    Livewire::actingAs($this->mod)
        ->test(ThreadView::class, ['thread' => $thread])
        ->set('replyBody', 'Locked, see rules.')
        ->call('reply')
        ->assertHasNoErrors();
});

it('sees only reports from its sections in the queue', function () {
    $mine = threadIn($this->assigned)->posts()->first();
    $notMine = threadIn($this->other)->posts()->first();
    $reporter = userWithRole(Role::MEMBER);
    $r1 = Report::create(['post_id' => $mine->id, 'reporter_id' => $reporter->id, 'reason' => 'mine-report']);
    $r2 = Report::create(['post_id' => $notMine->id, 'reporter_id' => $reporter->id, 'reason' => 'other-report']);

    Livewire::actingAs($this->mod)
        ->test(ReportList::class)
        ->assertSee('mine-report')
        ->assertDontSee('other-report');
    Livewire::actingAs($this->mod)->test(ReportPanel::class, ['report' => $r1])->call('resolve', 'resolved');
    Livewire::actingAs($this->mod)->test(ReportPanel::class, ['report' => $r2])->assertForbidden();
    $this->actingAs($this->mod)->get(route('moderation.reports.show', $r2))->assertForbidden();

    expect($r1->fresh()->status)->toBe('resolved')->and($r2->fresh()->status)->toBe('open');
});

it('cannot access admin pages', function () {
    $this->actingAs($this->mod)->get(route('admin.users'))->assertForbidden();
    $this->actingAs($this->mod)->get(route('moderation'))->assertOk();
});
