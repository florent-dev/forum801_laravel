<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModerationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['moderator_id', 'action', 'subject_type', 'subject_id', 'section_id', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(User $moderator, string $action, Model $subject, ?int $sectionId = null, array $details = []): self
    {
        return static::create([
            'moderator_id' => $moderator->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'section_id' => $sectionId,
            'details' => $details ?: null,
        ]);
    }
}
