<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    public const PER_PAGE = 15;

    protected $fillable = ['thread_id', 'user_id', 'body', 'body_html', 'is_hidden', 'edited_at', 'ip'];


    protected $hidden = ['search_vector', 'ip'];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'edited_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        // Deleted threads stay reachable so moderators can review and restore them
        return $this->belongsTo(Thread::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Plain text excerpt that respects moderation for the current viewer
    public function excerpt(int $limit = 220): string
    {
        if ($this->moderated_at && ! \Illuminate\Support\Facades\Gate::allows('viewContent', $this)) {
            return '⚖️ '.__('Moderated by :name, reason: :reason', ['name' => $this->moderator?->name ?? __('a moderator'), 'reason' => $this->moderation_reason]);
        }

        // Spoilers stay hidden in excerpts
        $text = preg_replace('/\[spoiler\].*?\[\/spoiler\]/is', '▒▒▒', $this->body);

        return \Illuminate\Support\Str::limit($text, $limit);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function hider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function poll(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Poll::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->latest('id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
