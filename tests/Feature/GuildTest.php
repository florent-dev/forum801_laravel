<?php

use App\Livewire\GuildDirectory;
use App\Livewire\GuildManage;
use App\Livewire\GuildPage;
use App\Livewire\SectionList;
use App\Livewire\ThreadView;
use App\Models\Guild;
use App\Models\GuildApplication;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\ForumService;
use App\Services\GuildService;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = userWithRole(Role::MEMBER);
    $this->guild = app(GuildService::class)->create($this->owner, ['name' => 'Cheese Knights', 'tag' => 'chk', 'community' => 'en', 'recruitment' => 'application', 'description' => '**Welcome**']);
    $this->owner = $this->owner->fresh();
});

function joinGuild(Guild $guild, User $user, string $role = 'member'): User
{
    $guild->members()->create(['user_id' => $user->id, 'role' => $role]);

    return $user->fresh();
}

it('creates a guild with its own private forum section', function () {
    Livewire::actingAs($founder = userWithRole(Role::MEMBER))->test(GuildDirectory::class)
        ->set('name', 'Mouse Squad')->set('tag', 'ms')->set('newCommunity', 'fr')->set('recruitment', 'open')
        ->call('create')->assertHasNoErrors()->assertRedirect();

    $guild = Guild::where('name', 'Mouse Squad')->first();
    expect($guild->tag)->toBe('MS')->and($guild->community)->toBe('fr')
        ->and($guild->roleOf($founder))->toBe('owner')
        ->and($guild->section->visibility)->toBe('private')
        ->and($this->guild->description_html)->toContain('<strong>Welcome</strong>');
});

it('lets players join several guilds up to the limit and validates names', function () {
    Livewire::actingAs($this->owner)->test(GuildDirectory::class)->set('name', 'Second guild')->set('tag', 'sec')->call('create')->assertHasNoErrors();
    expect($this->owner->fresh()->guilds()->pluck('name')->all())->toBe(['Cheese Knights', 'Second guild']);

    config(['forum.max_guilds_per_user' => 2]);
    Livewire::actingAs($this->owner->fresh())->test(GuildDirectory::class)->set('name', 'Third guild')->set('tag', 'thr')->call('create')->assertForbidden();
    $this->guild->update(['recruitment' => 'open']);
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(GuildDirectory::class)->set('name', 'Cheese Knights')->set('tag', 'x1')->call('create')->assertHasErrors('name');
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(GuildDirectory::class)->set('name', 'Valid')->set('tag', 'a b')->call('create')->assertHasErrors('tag');
});

it('joins open guilds instantly', function () {
    $this->guild->update(['recruitment' => 'open']);
    $player = userWithRole(Role::MEMBER);

    Livewire::actingAs($player)->test(GuildPage::class, ['guild' => $this->guild])->call('join')->assertRedirect();

    expect($this->guild->roleOf($player))->toBe('member');
});

it('handles applications with notifications', function () {
    $player = userWithRole(Role::MEMBER);

    Livewire::actingAs($player)->test(GuildPage::class, ['guild' => $this->guild])->call('join')->assertHasErrors('applicationMessage');
    Livewire::actingAs($player)->test(GuildPage::class, ['guild' => $this->guild])->set('applicationMessage', 'I love cheese')->call('join')->assertHasNoErrors();
    $application = GuildApplication::first();
    expect($this->owner->notifications()->where('data->kind', 'guild')->exists())->toBeTrue();

    Livewire::actingAs($this->owner)->test(GuildManage::class, ['guild' => $this->guild])->call('decide', $application->id, true);
    expect($this->guild->roleOf($player))->toBe('member')
        ->and($player->notifications()->where('data->kind', 'guild')->exists())->toBeTrue();
});

it('refuses applications to closed guilds and duplicates', function () {
    $player = userWithRole(Role::MEMBER);
    Livewire::actingAs($player)->test(GuildPage::class, ['guild' => $this->guild])->set('applicationMessage', 'Hi')->call('join');
    Livewire::actingAs($player)->test(GuildPage::class, ['guild' => $this->guild])->set('applicationMessage', 'Hi again')->call('join')->assertHasErrors('application');

    $this->guild->update(['recruitment' => 'closed']);
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(GuildPage::class, ['guild' => $this->guild])->set('applicationMessage', 'Hi')->call('join')->assertForbidden();
});

it('lets officers decide applications and kick members, but not officers', function () {
    $officer = joinGuild($this->guild, userWithRole(Role::MEMBER), 'officer');
    $otherOfficer = joinGuild($this->guild, userWithRole(Role::MEMBER), 'officer');
    $member = joinGuild($this->guild, userWithRole(Role::MEMBER));
    $memberRow = $this->guild->members()->where('user_id', $member->id)->first();
    $officerRow = $this->guild->members()->where('user_id', $otherOfficer->id)->first();

    Livewire::actingAs($officer)->test(GuildManage::class, ['guild' => $this->guild])->call('kick', $officerRow->id)->assertForbidden();
    Livewire::actingAs($officer)->test(GuildManage::class, ['guild' => $this->guild])->call('setRole', $memberRow->id, 'officer')->assertForbidden();
    Livewire::actingAs($officer)->test(GuildManage::class, ['guild' => $this->guild])->call('kick', $memberRow->id);

    expect($this->guild->roleOf($member))->toBeNull();
});

it('lets the owner promote, demote and transfer ownership', function () {
    $member = joinGuild($this->guild, userWithRole(Role::MEMBER));
    $row = $this->guild->members()->where('user_id', $member->id)->first();

    Livewire::actingAs($this->owner)->test(GuildManage::class, ['guild' => $this->guild])->call('setRole', $row->id, 'officer');
    expect($this->guild->roleOf($member))->toBe('officer');

    Livewire::actingAs($this->owner)->test(GuildManage::class, ['guild' => $this->guild])->call('transfer', $row->id);
    expect($this->guild->roleOf($member))->toBe('owner')->and($this->guild->roleOf($this->owner))->toBe('officer');
});

it('keeps members out of manage pages and the owner from leaving', function () {
    $member = joinGuild($this->guild, userWithRole(Role::MEMBER));

    $this->actingAs($member)->get(route('guilds.manage', $this->guild))->assertForbidden();
    Livewire::actingAs($this->owner)->test(GuildPage::class, ['guild' => $this->guild])->call('leave')->assertHasErrors('guild');
    Livewire::actingAs($member)->test(GuildPage::class, ['guild' => $this->guild])->call('leave')->assertRedirect(route('guilds.index'));
    expect($this->guild->roleOf($member))->toBeNull();
});

it('restricts the guild forum to members unless made public', function () {
    $section = $this->guild->section;
    $stranger = userWithRole(Role::MEMBER);

    $this->actingAs($stranger)->get(route('sections.show', $section))->assertForbidden();
    $this->actingAs($this->owner)->get(route('sections.show', $section))->assertOk();

    $this->guild->update(['forum_public' => true]);
    $this->actingAs($stranger)->get(route('sections.show', $section))->assertOk();
    $this->get(route('sections.show', $section))->assertOk();
});

it('gives officers moderation of their guild forum only', function () {
    $officer = joinGuild($this->guild, userWithRole(Role::MEMBER), 'officer');
    $thread = app(ForumService::class)->createThread($this->owner, $this->guild->section, 'Guild talk', 'Hi');
    $public = threadIn(Section::factory()->create());

    Livewire::actingAs($officer)->test(ThreadView::class, ['thread' => $thread])->call('toggleLock')->assertHasNoErrors();
    expect($thread->fresh()->is_locked)->toBeTrue();
    Livewire::actingAs($officer)->test(ThreadView::class, ['thread' => $public])->call('toggleLock')->assertForbidden();
    $this->actingAs($officer)->get(route('moderation'))->assertForbidden();
});

it('lists every guild forum after the sections whatever the community', function () {
    session(['community' => 'fr']);
    $other = app(GuildService::class)->create(userWithRole(Role::MEMBER), ['name' => 'Night Owls', 'tag' => 'owl', 'community' => 'de']);
    joinGuild($other, $this->owner);

    Livewire::actingAs($this->owner->fresh())->test(SectionList::class)->assertSee('Cheese Knights')->assertSee('Night Owls')->assertSee('My guilds');
    Livewire::actingAs(userWithRole(Role::MEMBER))->test(SectionList::class)->assertDontSee('My guild');
});

it('keeps guild forums out of the regular lists', function () {
    expect(Section::forum()->pluck('id'))->not->toContain($this->guild->section->id);
});

it('disbands a guild and closes its forum', function () {
    $sectionId = $this->guild->section->id;

    Livewire::actingAs($this->owner)->test(GuildManage::class, ['guild' => $this->guild])->call('deleteGuild')->assertRedirect(route('guilds.index'));

    expect(Guild::count())->toBe(0)->and(Section::find($sectionId)->guild_id)->toBeNull();
    $this->actingAs($this->owner->fresh())->get(route('sections.show', $sectionId ? Section::find($sectionId) : null))->assertForbidden();
});

it('shows the directory and public pages', function () {
    $this->get(route('guilds.index'))->assertOk()->assertSee('Cheese Knights');
    $this->get(route('guilds.show', $this->guild))->assertOk()->assertSee('[CHK]')->assertSeeHtml('<strong>Welcome</strong>');
});

it('shows guild page and manage buttons in the guild forum header', function () {
    $member = joinGuild($this->guild, userWithRole(Role::MEMBER));

    $this->actingAs($this->owner)->get(route('sections.show', $this->guild->section))
        ->assertOk()->assertSee(route('guilds.show', $this->guild))->assertSee(route('guilds.manage', [$this->guild, 'tab' => 'members']))
        ->assertDontSee(route('feeds.section', $this->guild->section));
    $this->actingAs($member)->get(route('sections.show', $this->guild->section))
        ->assertSee(route('guilds.show', $this->guild))->assertDontSee(route('guilds.manage', $this->guild));
});

it('keeps the guild forum in the left bar on guild pages', function () {
    $this->actingAs($this->owner)->get(route('guilds.manage', $this->guild))->assertOk()->assertSeeLivewire('thread-list');
    $this->actingAs($this->owner)->get(route('guilds.show', $this->guild))->assertOk()->assertSeeLivewire('thread-list');
    // Strangers cannot read a members-only forum, so they get the sections list
    $this->actingAs(userWithRole(Role::MEMBER))->get(route('guilds.show', $this->guild))->assertOk()->assertSeeLivewire('section-list');
});
