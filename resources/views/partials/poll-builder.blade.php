{{-- Poll composer, bound to the BuildsPoll trait of the parent component --}}
<div class="field" style="margin-top:8px">
    <label class="switch"><input type="checkbox" wire:model.live="withPoll"><span class="slider"></span><span class="switch-label">📊 {{ __('Attach a poll') }}</span></label>
</div>
@if ($withPoll)
    <div class="poll-box poll-builder">
        <div class="field"><label for="poll-question-{{ $this->getId() }}">{{ __('Question') }}</label>
            <input id="poll-question-{{ $this->getId() }}" type="text" wire:model="pollQuestion" maxlength="200" placeholder="{{ __('What do you want to ask?') }}">
            @error('pollQuestion')<div class="error">{{ $message }}</div>@enderror</div>

        <label>{{ __('Answers') }}</label>
        @foreach ($pollOptions as $i => $option)
            <div class="poll-builder-option" wire:key="opt{{ $i }}">
                <span class="muted">{{ $i + 1 }}.</span>
                <input type="text" wire:model="pollOptions.{{ $i }}" maxlength="120" placeholder="{{ __('Answer :number', ['number' => $i + 1]) }}">
                @if (count($pollOptions) > 2)<button type="button" class="icon-btn" wire:click="removeOption({{ $i }})" title="{{ __('Remove') }}">✕</button>@endif
            </div>
        @endforeach
        @error('pollOptions')<div class="error">{{ $message }}</div>@enderror
        @error('pollOptions.*')<div class="error">{{ $message }}</div>@enderror
        @if (count($pollOptions) < 10)<button type="button" class="linkbtn" wire:click="addOption">＋ {{ __('Add an answer') }}</button>@endif

        <div class="poll-builder-grid">
            <div>
                <label class="switch"><input type="checkbox" wire:model.live="pollMultiple"><span class="slider"></span><span class="switch-label">{{ __('Several answers allowed') }}</span></label>
                @if ($pollMultiple)
                    <label class="inline-field">{{ __('At most') }}
                        <select wire:model="pollMax" style="width:auto">
                            <option value="">{{ __('no limit') }}</option>
                            @for ($n = 2; $n <= max(2, count($pollOptions)); $n++)<option value="{{ $n }}">{{ $n }}</option>@endfor
                        </select>
                    </label>
                    @error('pollMax')<div class="error">{{ $message }}</div>@enderror
                @endif
            </div>
            <div>
                <label class="inline-field">⏱️ {{ __('Ends') }}
                    <select wire:model.live="pollEnd" style="width:auto">
                        @foreach (['never' => __('never'), '1h' => __('in 1 hour'), '1d' => __('in 1 day'), '3d' => __('in 3 days'), '1w' => __('in 1 week'), '2w' => __('in 2 weeks'), '1m' => __('in 1 month'), 'custom' => __('on a date…')] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($pollEnd === 'custom')
                    <input type="datetime-local" wire:model="pollEndAt" min="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}" max="{{ now()->addYear()->format('Y-m-d\TH:i') }}" style="margin-top:4px">
                    @error('pollEndAt')<div class="error">{{ $message }}</div>@enderror
                @endif
            </div>
            <div>
                <label class="inline-field">👁️ {{ __('Results visible') }}
                    <select wire:model="pollResults" style="width:auto">
                        <option value="always">{{ __('always') }}</option>
                        <option value="after_vote">{{ __('after voting') }}</option>
                        <option value="after_close">{{ __('when the poll ends') }}</option>
                    </select>
                </label>
                @error('pollResults')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="switch"><input type="checkbox" wire:model="pollShowVoters"><span class="slider"></span><span class="switch-label">{{ __('Public votes (show who voted)') }}</span></label>
                <label class="switch" style="margin-top:6px"><input type="checkbox" wire:model="pollAllowChange"><span class="slider"></span><span class="switch-label">{{ __('Voters can change their vote') }}</span></label>
            </div>
        </div>
    </div>
@endif
