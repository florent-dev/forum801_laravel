<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class ForumActivity extends Notification
{
    // Kinds: reply, mention, quote, message, moderation
    public function __construct(
        public string $kind,
        public string $key,
        public array $params,
        public string $url,
        public ?User $actor = null,
    ) {
    }

    // Nothing reaches a user from someone they blocked
    public function via(object $notifiable): array
    {
        if ($this->actor && $notifiable instanceof User && $notifiable->hasBlocked($this->actor)) {
            return [];
        }

        return ['database'];
    }

    // The key and params are stored so the text is translated in the reader's language
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'key' => $this->key,
            'params' => $this->params,
            'text' => __($this->key, $this->params, 'en'),
            'url' => $this->url,
            'actor' => $this->actor?->name,
            'actor_color' => $this->actor?->displayColor(),
        ];
    }

    // Translated text of a stored notification, falling back to the English copy
    public static function textOf(array $data): string
    {
        return isset($data['key']) ? __($data['key'], $data['params'] ?? []) : ($data['text'] ?? '');
    }
}
