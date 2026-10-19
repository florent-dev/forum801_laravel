<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Carbon;

// Poll composer shared by the new thread and reply forms
trait BuildsPoll
{
    public const POLL_ENDS = ['never' => null, '1h' => 1 / 24, '1d' => 1, '3d' => 3, '1w' => 7, '2w' => 14, '1m' => 30];

    public bool $withPoll = false;

    public string $pollQuestion = '';

    public array $pollOptions = ['', ''];

    public bool $pollMultiple = false;

    public string $pollMax = '';

    public string $pollEnd = 'never';

    public string $pollEndAt = '';

    public string $pollResults = 'always';

    public bool $pollShowVoters = false;

    public bool $pollAllowChange = true;

    public function addOption(): void
    {
        if (count($this->pollOptions) < 10) {
            $this->pollOptions[] = '';
        }
    }

    public function removeOption(int $index): void
    {
        if (count($this->pollOptions) > 2) {
            unset($this->pollOptions[$index]);
            $this->pollOptions = array_values($this->pollOptions);
        }
    }

    protected function pollRules(): array
    {
        if (! $this->withPoll) {
            return [];
        }
        $this->pollOptions = array_values(array_filter(array_map('trim', $this->pollOptions), fn ($o) => $o !== ''));

        return [
            'pollQuestion' => ['required', 'string', 'min:3', 'max:200'],
            'pollOptions' => ['array', 'min:2', 'max:10'],
            'pollOptions.*' => ['string', 'max:120', 'distinct'],
            'pollMax' => ['nullable', 'integer', 'min:2', 'max:'.max(2, count($this->pollOptions))],
            'pollEnd' => ['required', 'in:'.implode(',', [...array_keys(self::POLL_ENDS), 'custom'])],
            'pollEndAt' => [$this->pollEnd === 'custom' ? 'required' : 'nullable', 'date', 'after:now', 'before:+1 year'],
            'pollResults' => ['required', 'in:always,after_vote,after_close'],
        ];
    }

    protected function pollPayload(): ?array
    {
        if (! $this->withPoll) {
            return null;
        }
        $closesAt = match (true) {
            $this->pollEnd === 'custom' => Carbon::parse($this->pollEndAt),
            self::POLL_ENDS[$this->pollEnd] !== null => now()->addMinutes((int) round(self::POLL_ENDS[$this->pollEnd] * 1440)),
            default => null,
        };

        return [
            'question' => trim($this->pollQuestion),
            'options' => $this->pollOptions,
            'multiple' => $this->pollMultiple,
            'max_choices' => $this->pollMultiple && $this->pollMax !== '' ? (int) $this->pollMax : null,
            'closes_at' => $closesAt,
            'results' => $this->pollResults,
            'show_voters' => $this->pollShowVoters,
            'allow_change' => $this->pollAllowChange,
        ];
    }

    protected function resetPoll(): void
    {
        $this->reset('withPoll', 'pollQuestion', 'pollOptions', 'pollMultiple', 'pollMax', 'pollEnd', 'pollEndAt', 'pollResults', 'pollShowVoters', 'pollAllowChange');
    }

    protected function pollAttributes(): array
    {
        return ['pollQuestion' => __('question'), 'pollOptions' => __('answers'), 'pollEndAt' => __('end date'), 'pollMax' => __('maximum answers')];
    }
}
