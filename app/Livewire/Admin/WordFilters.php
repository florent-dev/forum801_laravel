<?php

namespace App\Livewire\Admin;

use App\Models\ModerationLog;
use App\Models\WordFilter;
use App\Services\WordFilterService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class WordFilters extends Component
{
    public string $phrase = '';

    public string $action = 'replace';

    public bool $wholeWord = true;

    public string $sample = '';

    public ?array $result = null;

    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function add(WordFilterService $words): void
    {
        $this->authorize('admin');
        $this->phrase = trim($this->phrase);
        $this->validate([
            'phrase' => ['required', 'string', 'min:2', 'max:100', Rule::unique('word_filters', 'phrase')],
            'action' => ['required', Rule::in(WordFilter::ACTIONS)],
        ]);
        $filter = WordFilter::create(['phrase' => $this->phrase, 'action' => $this->action, 'whole_word' => $this->wholeWord, 'created_by' => auth()->id()]);
        ModerationLog::record(auth()->user(), 'word_filter_add', $filter, null, ['phrase' => $filter->phrase, 'action' => $filter->action]);
        $words->forget();
        $this->reset('phrase');
        $this->refreshTest($words);
    }

    public function setAction(int $id, string $action, WordFilterService $words): void
    {
        $this->authorize('admin');
        abort_unless(in_array($action, WordFilter::ACTIONS, true), 422);
        WordFilter::findOrFail($id)->update(['action' => $action]);
        $words->forget();
        $this->refreshTest($words);
    }

    public function toggleWholeWord(int $id, WordFilterService $words): void
    {
        $this->authorize('admin');
        $filter = WordFilter::findOrFail($id);
        $filter->update(['whole_word' => ! $filter->whole_word]);
        $words->forget();
        $this->refreshTest($words);
    }

    public function remove(int $id, WordFilterService $words): void
    {
        $this->authorize('admin');
        $filter = WordFilter::findOrFail($id);
        ModerationLog::record(auth()->user(), 'word_filter_remove', $filter, null, ['phrase' => $filter->phrase]);
        $filter->delete();
        $words->forget();
        $this->refreshTest($words);
    }

    public function test(WordFilterService $words): void
    {
        $this->validate(['sample' => ['required', 'string', 'max:5000']]);
        $this->refreshTest($words);
    }

    protected function refreshTest(WordFilterService $words): void
    {
        $this->result = trim($this->sample) !== '' ? $words->test($this->sample) : null;
    }

    public function render()
    {
        return view('livewire.admin.word-filters', ['filters' => WordFilter::with('creator')->orderBy('phrase')->get()]);
    }
}
