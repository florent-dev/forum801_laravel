<?php

// Every UI string used in the code must exist in every community language
dataset('communityLocales', ['fr', 'es', 'pt_BR', 'de', 'pl', 'ru', 'tr', 'nl', 'it', 'hu', 'ro']);

it('lists every community locale in the dataset', function () {
    expect(collect(config('forum.communities'))->pluck('locale')->reject(fn ($l) => $l === 'en')->unique()->sort()->values()->all())
        ->toBe(collect(['fr', 'es', 'pt_BR', 'de', 'pl', 'ru', 'tr', 'nl', 'it', 'hu', 'ro'])->sort()->values()->all());
});
function usedTranslationKeys(): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app')));
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    foreach ([$files, $views] as $iterator) {
        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            preg_match_all("/(?:__|trans_choice|@lang)\\(\\s*'((?:\\\\'|[^'])*)'/", $source, $m);
            foreach ($m[1] as $key) {
                $keys[] = str_replace("\\'", "'", $key);
            }
            // Notification keys are stored untranslated and translated when displayed
            preg_match_all("/'((?:[^']*):(?:actor|guild|reason|title|subject|role)\\b[^']*)'/", $source, $n);
            if (str_contains($file->getPathname(), 'Services')) {
                array_push($keys, ...$n[1]);
            }
        }
    }
    // Labels translated dynamically with __($label)
    $dynamic = [
        ...array_values(\App\Models\Thread::PREFIXES),
        ...array_values(\App\Livewire\ThreadList::SORTS),
        ...array_values(\App\Livewire\SanctionModal::DURATIONS), 'Permanently',
        'Newcomer', 'Apprentice', 'Member', 'Regular', 'Veteran', 'Legend',
        'Open', 'Resolved', 'Dismissed', 'All',
        'Spam', 'Insults or harassment', 'Off topic', 'Double post', 'Inappropriate content',
        'Like', 'Cheese', 'Laugh', 'Heart', 'Wow', 'Warning', 'Mute', 'Suspension',
        'Owner', 'Officer', 'owner', 'officer', 'member', 'Public', 'Private',
    ];

    return array_values(array_unique(array_filter([...$keys, ...$dynamic], fn ($k) => $k !== '' && ! str_starts_with($k, 'exists:'))));
}

it('has every UI string translated in every community language', function (string $locale) {
    $translations = json_decode(file_get_contents(lang_path("{$locale}.json")), true);
    $missing = array_values(array_filter(usedTranslationKeys(), fn ($key) => ! array_key_exists($key, $translations)));

    expect($missing)->toBe([], "Missing in {$locale}: ".implode(' | ', array_slice($missing, 0, 15)));
})->with('communityLocales');

it('keeps placeholders in translations', function (string $locale) {
    $translations = json_decode(file_get_contents(lang_path("{$locale}.json")), true);
    $broken = [];
    foreach (usedTranslationKeys() as $key) {
        preg_match_all('/:[a-z_]+/', $key, $placeholders);
        foreach (array_unique($placeholders[0]) as $placeholder) {
            if (isset($translations[$key]) && ! str_contains($translations[$key], $placeholder)) {
                $broken[] = $key;
            }
        }
    }

    expect($broken)->toBe([]);
})->with('communityLocales');
