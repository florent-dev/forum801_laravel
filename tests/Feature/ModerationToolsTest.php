<?php

use App\Livewire\ModerationQueue;
use App\Livewire\UserFile;
use App\Models\Post;
use App\Models\Report;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Models\UserNote;
use App\Services\ModerationService;
use App\Services\SanctionService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Section::factory()->create(['name' => 'Cheese talk']);
    $this->other = Section::factory()->create(['name' => 'Elsewhere']);
    $this->author = userWithRole(Role::MEMBER);
    $this->author->update(['name' => 'trouble']);
    $this->thread = threadIn($this->section, $this->author);
    $this->post = $this->thread->posts()->first();
    $this->post->update(['body' => 'buy cheap cheese here', 'ip' => '198.51.100.7']);
    $this->mod = userWithRole(Role::MODERATOR, $this->section);
    $this->senior = userWithRole(Role::SENIOR_MODERATOR);
});

it('keeps the user file for staff only', function () {
    $this->get(route('moderation.users.show', $this->author))->assertRedirect();
    $this->actingAs(userWithRole(Role::MEMBER))->get(route('moderation.users.show', $this->author))->assertForbidden();
    $this->actingAs($this->mod)->get(route('moderation.users.show', $this->author))->assertOk()->assertSee('trouble');
});

it('shows sanctions, reports and counts on the user file', function () {
    app(SanctionService::class)->apply($this->senior, $this->author, 'warning', null, 'Spam links');
    Report::create(['post_id' => $this->post->id, 'reporter_id' => $this->senior->id, 'reason' => 'Advertising']);

    Livewire::actingAs($this->mod)->test(UserFile::class, ['user' => $this->author])
        ->assertSee('Spam links')
        ->assertSee('Advertising')
        ->assertSee('1 open');
});

it('shows IPs and linked accounts only to those allowed to see IPs', function () {
    $twin = User::factory()->create(['name' => 'twinaccount']);
    DB::table('user_ips')->insert([
        ['user_id' => $this->author->id, 'ip' => '198.51.100.7', 'hits' => 3, 'first_seen_at' => now(), 'last_seen_at' => now()],
        ['user_id' => $twin->id, 'ip' => '198.51.100.7', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()],
    ]);

    Livewire::actingAs($this->mod)->test(UserFile::class, ['user' => $this->author])->assertDontSee('198.51.100.7')->assertDontSee('twinaccount');
    Livewire::actingAs($this->senior)->test(UserFile::class, ['user' => $this->author])->assertSee('198.51.100.7')->assertSee('twinaccount');
});

it('lets moderators write staff notes that the user never sees', function () {
    Livewire::actingAs($this->mod)->test(UserFile::class, ['user' => $this->author])
        ->set('note', 'Warned in private about links')->call('addNote')->assertHasNoErrors()
        ->assertSee('Warned in private about links');

    $this->actingAs($this->author)->get(route('profile', $this->author))->assertDontSee('Warned in private');
    expect(UserNote::where('user_id', $this->author->id)->count())->toBe(1);
});

it('lets only the note author or an admin delete a note', function () {
    $note = UserNote::create(['user_id' => $this->author->id, 'author_id' => $this->mod->id, 'body' => 'Keep an eye']);

    Livewire::actingAs($this->senior)->test(UserFile::class, ['user' => $this->author])->call('deleteNote', $note->id)->assertForbidden();
    Livewire::actingAs(userWithRole(Role::ADMINISTRATOR))->test(UserFile::class, ['user' => $this->author])->call('deleteNote', $note->id);

    expect(UserNote::count())->toBe(0);
});

it('removes staff notes when the account is anonymized', function () {
    UserNote::create(['user_id' => $this->author->id, 'author_id' => $this->mod->id, 'body' => 'Note']);
    app(\App\Services\PrivacyService::class)->anonymize(userWithRole(Role::ADMINISTRATOR), $this->author);

    expect(UserNote::count())->toBe(0);
});

it('searches posts by author, including hidden and deleted ones', function () {
    $deleted = Post::factory()->create(['thread_id' => $this->thread->id, 'user_id' => $this->author->id, 'body' => 'a deleted reply']);
    app(ModerationService::class)->deletePost($this->mod, $deleted);

    Livewire::actingAs($this->mod)->test(ModerationQueue::class)->set('tab', 'search')->set('user', 'TROUBLE')
        ->assertSee('buy cheap cheese here')->assertSee('a deleted reply');
    Livewire::actingAs($this->mod)->test(ModerationQueue::class)->set('tab', 'search')->set('postStatus', 'deleted')
        ->assertSee('a deleted reply')->assertDontSee('buy cheap cheese here');
});

it('searches by text and stays within the moderated sections', function () {
    threadIn($this->other)->posts()->first()->update(['body' => 'cheese from another place']);

    Livewire::actingAs($this->mod)->test(ModerationQueue::class)->set('tab', 'search')->set('text', 'cheese')
        ->assertSee('buy cheap cheese here')->assertDontSee('cheese from another place');
    Livewire::actingAs($this->senior)->test(ModerationQueue::class)->set('tab', 'search')->set('text', 'cheese')
        ->assertSee('cheese from another place');
});

it('searches by IP prefix only for those allowed to see IPs', function () {
    Livewire::actingAs($this->senior)->test(ModerationQueue::class)->set('tab', 'search')->set('ip', '198.51.')
        ->assertSee('buy cheap cheese here');

    // A section moderator cannot filter by IP: the filter is ignored and IPs are not shown
    Livewire::actingAs($this->mod)->test(ModerationQueue::class)->set('tab', 'search')->set('user', 'trouble')->set('ip', '10.')
        ->assertSee('buy cheap cheese here')->assertDontSee('198.51.100.7');
});

it('treats search input as plain text, not as a pattern', function () {
    Livewire::actingAs($this->senior)->test(ModerationQueue::class)->set('tab', 'search')->set('text', '%')
        ->assertDontSee('buy cheap cheese here');
});

it('computes moderation statistics per moderator', function () {
    $report = Report::create(['post_id' => $this->post->id, 'reporter_id' => $this->senior->id, 'reason' => 'Spam']);
    $report->forceFill(['created_at' => now()->subHours(2)])->save();
    app(ModerationService::class)->resolveReport($this->mod, $report, 'resolved');
    Report::create(['post_id' => $this->post->id, 'reporter_id' => userWithRole(Role::MEMBER)->id, 'reason' => 'Again']);

    $stats = app(\App\Services\ModerationInsights::class)->stats($this->mod, 30);

    expect($stats['received'])->toBe(2)
        ->and($stats['handled'])->toBe(1)
        ->and($stats['open'])->toBe(1)
        ->and((int) round($stats['avgSeconds'] / 3600))->toBe(2)
        ->and($stats['perModerator']->first()['user']->id)->toBe($this->mod->id)
        ->and($stats['topSections']->first()['section']->id)->toBe($this->section->id)
        ->and($stats['topUsers']->first()['user']->id)->toBe($this->author->id);

    Livewire::actingAs($this->mod)->test(ModerationQueue::class)->set('tab', 'stats')
        ->assertSee('Average handling time')->assertSee('Cheese talk')->assertSee('trouble');
});

it('ignores reports from sections the moderator does not handle', function () {
    $elsewhere = threadIn($this->other)->posts()->first();
    Report::create(['post_id' => $elsewhere->id, 'reporter_id' => $this->senior->id, 'reason' => 'Off topic']);

    expect(app(\App\Services\ModerationInsights::class)->stats($this->mod, 30)['received'])->toBe(0)
        ->and(app(\App\Services\ModerationInsights::class)->stats($this->senior, 30)['received'])->toBe(1);
});

it('links the user file from the post author menu for moderators', function () {
    Livewire::actingAs($this->mod)->test(\App\Livewire\ThreadView::class, ['thread' => $this->thread])
        ->assertSee(route('moderation.users.show', $this->author), false);
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(\App\Livewire\ThreadView::class, ['thread' => $this->thread])
        ->assertDontSee(route('moderation.users.show', $this->author), false);
});
