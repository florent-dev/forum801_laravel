<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    public const RESULTS = ['always', 'after_vote', 'after_close'];

    protected $fillable = ['thread_id', 'post_id', 'question', 'multiple', 'max_choices', 'closes_at', 'results_visibility', 'show_voters', 'allow_change'];

    protected $attributes = ['results_visibility' => 'always', 'show_voters' => false, 'allow_change' => true, 'multiple' => false];

    protected function casts(): array
    {
        return ['multiple' => 'boolean', 'closes_at' => 'datetime', 'show_voters' => 'boolean', 'allow_change' => 'boolean'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class)->withTrashed();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }

    // Results follow the visibility chosen by the poll author
    public function resultsVisibleFor(bool $hasVoted): bool
    {
        return match ($this->results_visibility) {
            'after_vote' => $hasVoted || $this->isClosed(),
            'after_close' => $this->isClosed(),
            default => true,
        };
    }
}
