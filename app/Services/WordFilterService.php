<?php

namespace App\Services;

use App\Models\WordFilter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class WordFilterService
{
    public function filters()
    {
        return Cache::rememberForever('word-filters', fn () => WordFilter::orderBy('phrase')->get());
    }

    public function forget(): void
    {
        Cache::forget('word-filters');
    }

    // Returns the cleaned text and the phrases that need a moderator review; throws on blocked words
    public function apply(string $text, string $field = 'body'): array
    {
        $review = [];
        foreach ($this->filters() as $filter) {
            if (! preg_match($filter->pattern(), $text)) {
                continue;
            }
            match ($filter->action) {
                'block' => throw ValidationException::withMessages([$field => __('Your text contains a forbidden word: “:word”.', ['word' => $filter->phrase])]),
                'replace' => $text = preg_replace_callback($filter->pattern(), fn ($m) => str_repeat('*', mb_strlen($m[0])), $text),
                default => $review[] = $filter->phrase,
            };
        }

        return [$text, array_values(array_unique($review))];
    }

    // Dry run used by the admin test box: what would happen to this text
    public function test(string $text): array
    {
        try {
            [$clean, $review] = $this->apply($text);

            return ['blocked' => null, 'text' => $clean, 'review' => $review];
        } catch (ValidationException $e) {
            return ['blocked' => collect($e->errors())->flatten()->first(), 'text' => $text, 'review' => []];
        }
    }
}
