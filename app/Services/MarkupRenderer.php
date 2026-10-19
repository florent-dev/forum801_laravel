<?php

namespace App\Services;

use App\Models\User;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Mews\Purifier\Facades\Purifier;

class MarkupRenderer
{
    public const MENTION_PATTERN = '/(?<![\w@\/])@([A-Za-z0-9_.\-]{3,30})/';

    // Convert user markdown to sanitized HTML
    public function render(string $markdown): string
    {
        $markdown = $this->linkMentions($this->expandSpoilers($this->expandColorTags($markdown)));
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
        $html = (string) $converter->convert($markdown);

        // Color spans were escaped by commonmark, restore whitelisted ones
        $html = preg_replace_callback(
            '/&lt;span class=(?:&quot;|")colordiv-(\d{1,2})(?:&quot;|")&gt;(.*?)&lt;\/span&gt;/s',
            fn ($m) => '<span class="colordiv-'.$m[1].'">'.$m[2].'</span>',
            $html
        );
        // Spoilers are restored after colors so a color inside a spoiler closes first
        $html = preg_replace_callback(
            '/&lt;span class=(?:&quot;|")spoiler(?:&quot;|")&gt;(.*?)&lt;\/span&gt;/s',
            fn ($m) => '<span class="spoiler">'.$m[1].'</span>',
            $html
        );

        return Purifier::clean($html, 'forum');
    }

    // Names mentioned with @name that belong to real users
    public function mentionedNames(string $markdown): array
    {
        preg_match_all(self::MENTION_PATTERN, $markdown, $m);
        $names = array_unique(array_map(fn ($n) => rtrim($n, '.-'), $m[1] ?? []));

        return $names ? User::whereIn('name', $names)->pluck('name')->all() : [];
    }

    // Turn [color=N]text[/color] into a safe span placeholder
    // [spoiler]text[/spoiler] becomes a blurred span revealed on click
    protected function expandSpoilers(string $text): string
    {
        return preg_replace('/\[spoiler\](.*?)\[\/spoiler\]/is', '<span class="spoiler">$1</span>', $text);
    }

    protected function expandColorTags(string $text): string
    {
        return preg_replace('/\[color=(\d{1,2})\](.*?)\[\/color\]/s', '<span class="colordiv-$1">$2</span>', $text);
    }

    // Turn @name into a profile link when the user exists
    protected function linkMentions(string $text): string
    {
        $known = array_flip($this->mentionedNames($text));
        if (! $known) {
            return $text;
        }

        return preg_replace_callback(self::MENTION_PATTERN, function ($m) use ($known) {
            $name = rtrim($m[1], '.-');
            $tail = substr($m[1], strlen($name));

            return isset($known[$name]) ? '[@'.$name.']('.route('profile', $name).')'.$tail : $m[0];
        }, $text);
    }
}
