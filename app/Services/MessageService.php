<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ForumActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function __construct(protected MarkupRenderer $renderer, protected WordFilterService $words)
    {
    }

    public function start(User $author, Collection $recipients, string $subject, string $body): Conversation
    {
        // Private messages cannot be reported, so only block and replace apply
        [$subject] = $this->words->apply($subject, 'subject');
        [$body] = $this->words->apply($body);
        $this->throttle($author);

        return DB::transaction(function () use ($author, $recipients, $subject, $body) {
            $conversation = Conversation::create(['subject' => $subject, 'user_id' => $author->id, 'last_message_at' => now()]);
            $conversation->participants()->attach($author->id, ['last_read_at' => now()]);
            $conversation->participants()->attach($recipients->pluck('id')->reject(fn ($id) => $id === $author->id)->unique()->all());
            $this->store($author, $conversation, $body);

            return $conversation;
        });
    }

    public function reply(User $author, Conversation $conversation, string $body): Message
    {
        [$body] = $this->words->apply($body);
        $this->throttle($author);

        return DB::transaction(fn () => $this->store($author, $conversation, $body));
    }

    public function addParticipant(Conversation $conversation, User $user): void
    {
        $existing = $conversation->participants()->where('users.id', $user->id)->first();
        $existing
            ? $conversation->participants()->updateExistingPivot($user->id, ['left_at' => null])
            : $conversation->participants()->attach($user->id);
    }

    public function leave(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['left_at' => now()]);
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    protected function store(User $author, Conversation $conversation, string $body): Message
    {
        $message = $conversation->messages()->create([
            'user_id' => $author->id,
            'body' => $body,
            'body_html' => $this->renderer->render($body),
        ]);
        $conversation->forceFill(['last_message_at' => $message->created_at])->save();
        $this->markRead($conversation, $author);

        foreach ($conversation->activeParticipants()->where('users.id', '!=', $author->id)->get() as $user) {
            // One unread notification per conversation is enough
            $pending = $user->unreadNotifications()->where('data->kind', 'message')->where('data->url', route('messages.show', $conversation))->exists();
            if (! $pending) {
                $user->notify(new ForumActivity('message', ':actor sent you a message: “:subject”', ['actor' => $author->name, 'subject' => $conversation->subject], route('messages.show', $conversation), $author));
            }
        }

        return $message;
    }

    // Messaging rate limit per user, set in Admin › Settings
    protected function throttle(User $user): void
    {
        $key = 'messaging:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, (int) \App\Support\ForumSettings::get('messages_per_minute'))) {
            throw ValidationException::withMessages(['body' => __('You are sending messages too fast. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        RateLimiter::hit($key, 60);
    }
}
