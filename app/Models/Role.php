<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const MEMBER = 'member';
    public const MODERATOR = 'moderator';
    public const SENIOR_MODERATOR = 'senior_moderator';
    public const PRIVATE_MODERATOR = 'private_moderator';
    public const SECTION_OWNER = 'section_owner';
    public const ADMINISTRATOR = 'administrator';

    // Roles that only make sense attached to a section
    public const SECTION_SCOPED = [self::MODERATOR, self::SECTION_OWNER];

    protected $fillable = ['name', 'label', 'scope', 'color', 'rank'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['id', 'scope_type', 'scope_id'])->withTimestamps();
    }
}
