<?php

use App\Livewire\ReportPanel;
use App\Livewire\ThreadView;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\Role;
use App\Models\Section;
use App\Services\ModerationService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create();
    $this->thread = threadIn($this->section);
    $this->post = $this->thread->posts()->first();
    $this->post->update(['body_html' => '<p>original words</p>']);
    $this->mod = userWithRole(Role::MODERATOR, $this->section);
});

it('moderates a post with a public note and keeps it visible', function () {
    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread])
        ->call('toggleModerated', $this->post->id)
        ->assertDispatched('open-modal')
        ->set('reason', 'Rude wording')
        ->call('confirmReason')
        ->assertHasNoErrors();

    expect($this->post->fresh()->moderated_by)->toBe($this->mod->id)
        ->and($this->post->fresh()->moderation_reason)->toBe('Rude wording')
        ->and(ModerationLog::where('action', 'post.moderate')->exists())->toBeTrue()
        ->and($this->post->user->notifications()->first()->data['text'])->toContain('Rude wording');

    auth()->logout();
    Livewire::test(ThreadView::class, ['thread' => $this->thread])
        ->assertDontSee('original words')
        ->assertSee('Moderated by '.$this->mod->name.', reason: Rude wording');
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $this->thread])->assertDontSee('original words');
    Livewire::actingAs($this->post->user)->test(ThreadView::class, ['thread' => $this->thread])->assertSee('original words');
    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread])->assertSee('original words');
});

it('does not leak moderated content through quotes, search, profiles and feeds', function () {
    $this->post->update(['body' => 'secret moderated sentence']);
    app(ModerationService::class)->setModerated($this->mod, $this->post, true, 'Nope');
    $member = userWithRole(Role::MEMBER);

    Livewire::actingAs($member)->test(ThreadView::class, ['thread' => $this->thread])->call('quote', $this->post->id)->assertForbidden();
    expect($this->get(route('search', ['q' => 'secret moderated sentence', 'in' => 'posts']))->viewData('results')->total())->toBe(0);
    $this->actingAs($member)->get(route('profile', $this->post->user))->assertDontSee('secret moderated sentence')->assertSee('Moderated by');
    auth()->logout();
    $this->get(route('feeds.latest'))->assertDontSee('secret moderated sentence');
});

it('requires a reason to moderate or hide', function (string $method) {
    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread])
        ->call($method, $this->post->id)
        ->call('confirmReason')
        ->assertHasErrors('reason');

    expect($this->post->fresh()->moderated_at)->toBeNull()->and($this->post->fresh()->is_hidden)->toBeFalse();
})->with(['toggleModerated', 'toggleHidden']);

it('refuses reasonless moderation at the service level too', function () {
    app(ModerationService::class)->setHidden($this->mod, $this->post, true, '  ');
})->throws(ValidationException::class);

it('removes a moderation note without a reason', function () {
    app(ModerationService::class)->setModerated($this->mod, $this->post, true, 'Too harsh');

    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread])
        ->call('toggleModerated', $this->post->id)
        ->assertNotDispatched('open-modal');

    expect($this->post->fresh()->moderated_at)->toBeNull()->and($this->post->fresh()->moderation_reason)->toBeNull();
});

it('stores who hid a post and why', function () {
    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread])
        ->call('toggleHidden', $this->post->id)
        ->set('reason', 'Spam link')
        ->call('confirmReason');

    expect($this->post->fresh()->hidden_by)->toBe($this->mod->id)->and($this->post->fresh()->hide_reason)->toBe('Spam link');
    Livewire::actingAs($this->post->user)->test(ThreadView::class, ['thread' => $this->thread])->assertSee('Spam link');
});

it('bulk moderates selected posts with one reason', function () {
    Post::factory()->count(2)->create(['thread_id' => $this->thread->id]);
    $ids = $this->thread->posts()->pluck('id')->map(fn ($id) => (string) $id)->all();

    Livewire::actingAs($this->mod)->test(ThreadView::class, ['thread' => $this->thread])
        ->call('toggleSelectMode')
        ->set('selected', $ids)
        ->call('bulk', 'moderate')
        ->set('reason', 'Off topic')
        ->call('confirmReason');

    expect(Post::whereNotNull('moderated_at')->where('moderation_reason', 'Off topic')->count())->toBe(3);
});

it('forbids members from moderating', function () {
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(ThreadView::class, ['thread' => $this->thread])
        ->call('toggleModerated', $this->post->id)
        ->assertForbidden();
});

it('moderates and resolves from the report bar', function () {
    $report = Report::create(['post_id' => $this->post->id, 'reporter_id' => userWithRole(Role::MEMBER)->id, 'reason' => 'Insulting']);

    Livewire::actingAs($this->mod)->test(ReportPanel::class, ['report' => $report])->call('moderateAndResolve');

    expect($this->post->fresh()->moderation_reason)->toBe('Insulting')
        ->and($this->post->fresh()->is_hidden)->toBeFalse()
        ->and($report->fresh()->status)->toBe('resolved');
});
