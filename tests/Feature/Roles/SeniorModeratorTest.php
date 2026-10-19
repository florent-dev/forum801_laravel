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
    $this->senior = userWithRole(Role::SENIOR_MODERATOR);
});

it('moderates every public section', function () {
    $thread = threadIn(Section::factory()->create());

    Livewire::actingAs($this->senior)
        ->test(ThreadView::class, ['thread' => $thread])
        ->call('toggleLock')
        ->assertHasNoErrors();

    expect($thread->fresh()->is_locked)->toBeTrue();
});

it('cannot moderate or see private sections', function () {
    $private = Section::factory()->private()->create();
    $thread = threadIn($private);

    $this->actingAs($this->senior)->get($thread->url())->assertForbidden();
});

it('handles reports and logs the action', function () {
    $post = threadIn(Section::factory()->create())->posts()->first();
    $report = Report::create(['post_id' => $post->id, 'reporter_id' => userWithRole(Role::MEMBER)->id, 'reason' => 'rude']);

    Livewire::actingAs($this->senior)->test(ReportList::class)->assertSee('rude');
    Livewire::actingAs($this->senior)->test(ReportPanel::class, ['report' => $report])->call('hideAndResolve');

    expect($report->fresh()->status)->toBe('resolved')
        ->and($report->fresh()->handled_by)->toBe($this->senior->id)
        ->and($post->fresh()->is_hidden)->toBeTrue()
        ->and(ModerationLog::pluck('action')->all())->toContain('post.hide', 'report.resolved');
});

it('cannot access admin pages', function () {
    $this->actingAs($this->senior)->get(route('admin.sections'))->assertForbidden();
});
