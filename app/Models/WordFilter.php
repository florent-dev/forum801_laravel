<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordFilter extends Model
{
    public const ACTIONS = ['block', 'replace', 'review'];

    protected $fillable = ['phrase', 'action', 'whole_word', 'created_by'];

    protected function casts(): array
    {
        return ['whole_word' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pattern(): string
    {
        $quoted = preg_quote($this->phrase, '/');

        return $this->whole_word ? '/(?<![\p{L}\p{N}_])'.$quoted.'(?![\p{L}\p{N}_])/iu' : '/'.$quoted.'/iu';
    }
}
