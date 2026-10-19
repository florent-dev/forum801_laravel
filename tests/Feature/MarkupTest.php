<?php

use App\Models\User;
use App\Services\MarkupRenderer;

beforeEach(fn () => $this->render = fn (string $md) => app(MarkupRenderer::class)->render($md));

it('renders basic markdown', function () {
    $html = ($this->render)("**bold** *it* ~~del~~\n\n> quote\n\n- item\n\n`code`");

    expect($html)->toContain('<strong>bold</strong>', '<em>it</em>', '<del>del</del>', '<blockquote>', '<li>item</li>', '<code>code</code>');
});

it('keeps whitelisted color tags only', function () {
    expect(($this->render)('[color=8]green[/color]'))->toContain('<span class="colordiv-8">green</span>')
        ->and(($this->render)('[color=99]nope[/color]'))->not->toContain('colordiv-99');
});

it('escapes raw html and dangerous links', function () {
    $html = ($this->render)("hello <iframe src=x></iframe> <a href=\"javascript:x\">a</a> [b](javascript:alert(1)) <span style=\"color:red\">s</span>");

    expect($html)->not->toContain('<iframe')->not->toContain('<a href="javascript')->not->toContain('<span style');
});

it('opens external links in a new tab safely', function () {
    expect(($this->render)('[site](https://example.com)'))->toContain('target="_blank"')->toContain('noopener');
});

it('links mentions of existing users only', function () {
    User::factory()->create(['name' => 'realmouse']);

    $html = ($this->render)('hi @realmouse and @ghostmouse, mail me at a@realmouse.com');

    expect($html)->toContain('href="'.route('profile', 'realmouse').'"')
        ->and(substr_count($html, '/u/realmouse'))->toBe(1)
        ->and($html)->not->toContain('/u/ghostmouse');
});

it('returns the names actually mentioned', function () {
    User::factory()->create(['name' => 'realmouse']);

    expect(app(MarkupRenderer::class)->mentionedNames('@realmouse @realmouse. @nobody'))->toBe(['realmouse']);
});

it('renders spoilers, with formatting and colors inside', function () {
    $html = ($this->render)('End: [spoiler]the **cat** was [color=14]guilty[/color][/spoiler]!');

    expect($html)->toContain('<span class="spoiler">the <strong>cat</strong> was <span class="colordiv-14">guilty</span></span>');
});

it('keeps spoiler syntax safe', function () {
    $html = ($this->render)('[spoiler]<script>alert(1)</script>[/spoiler] [SPOILER]upper[/SPOILER]');

    expect($html)->not->toContain('<script')->toContain('<span class="spoiler">upper</span>');
});

it('hides spoilers in excerpts', function () {
    $post = new App\Models\Post(['body' => 'The end: [spoiler]Bruce Willis is a ghost[/spoiler]']);

    expect($post->excerpt())->toBe('The end: ▒▒▒');
});
