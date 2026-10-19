<?php

namespace App\Services;

use App\Models\Guild;
use App\Models\ModerationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Personal data: export everything stored about a user, or anonymize the account
class PrivacyService
{
    public function export(User $user): array
    {
        $rows = fn (string $table, string $column = 'user_id', array $hide = []) => DB::table($table)->where($column, $user->id)->get()
            ->map(fn ($r) => collect((array) $r)->except($hide)->all())->all();

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => collect($user->getAttributes())->except(['password', 'remember_token'])->all(),
            'roles' => $user->roleAssignments()->map(fn ($r) => ['role' => $r->name, 'scope_type' => $r->pivot->scope_type, 'scope_id' => $r->pivot->scope_id])->values()->all(),
            'threads' => DB::table('threads')->where('user_id', $user->id)->get(['id', 'section_id', 'title', 'created_at', 'deleted_at'])->all(),
            'posts' => DB::table('posts')->where('user_id', $user->id)->orderBy('id')->get(['id', 'thread_id', 'body', 'ip', 'created_at', 'edited_at', 'deleted_at'])->all(),
            'post_revisions' => $rows('post_revisions'),
            'messages' => DB::table('messages')->where('user_id', $user->id)->orderBy('id')->get(['id', 'conversation_id', 'body', 'created_at'])->all(),
            'conversations' => $rows('conversation_user'),
            'reactions' => $rows('reactions'),
            'poll_votes' => $rows('poll_votes'),
            'bookmarks' => $rows('bookmarks'),
            'subscriptions' => $rows('thread_subscriptions'),
            'friendships' => DB::table('friendships')->where('user_id', $user->id)->orWhere('friend_id', $user->id)->get()->all(),
            'blocked_users' => $rows('user_blocks'),
            'ignored_users' => $rows('user_ignores'),
            'guilds' => $rows('guild_members'),
            'guild_applications' => $rows('guild_applications'),
            'reports_sent' => $rows('reports', 'reporter_id'),
            'sanctions' => $rows('sanctions'),
            'ip_addresses' => $rows('user_ips'),
            'security_events' => $rows('security_events'),
            'notifications' => DB::table('notifications')->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->get(['type', 'data', 'read_at', 'created_at'])->all(),
        ];
    }

    // Removes everything personal but keeps the posts so threads stay readable, signed by a placeholder name
    public function anonymize(User $admin, User $user, bool $deletePosts = false): void
    {
        DB::transaction(function () use ($admin, $user, $deletePosts) {
            foreach ($user->guildMemberships()->where('role', 'owner')->get() as $membership) {
                $this->handOver(Guild::find($membership->guild_id), $user);
            }
            foreach (['guild_members', 'guild_applications', 'user_ips', 'user_notes', 'bookmarks', 'thread_subscriptions', 'thread_reads', 'reactions', 'sessions'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }
            DB::table('friendships')->where('user_id', $user->id)->orWhere('friend_id', $user->id)->delete();
            DB::table('user_blocks')->where('user_id', $user->id)->orWhere('blocked_id', $user->id)->delete();
            DB::table('user_ignores')->where('user_id', $user->id)->orWhere('ignored_id', $user->id)->delete();
            DB::table('notifications')->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->delete();
            DB::table('posts')->where('user_id', $user->id)->update(['ip' => null]);
            DB::table('security_events')->where('user_id', $user->id)->update(['ip' => null, 'user_agent' => null]);
            DB::table('conversation_user')->where('user_id', $user->id)->whereNull('left_at')->update(['left_at' => now()]);
            DB::table('role_user')->where('user_id', $user->id)->delete();
            if ($deletePosts) {
                $user->posts()->update(['deleted_at' => now()]);
            }

            $user->forceFill([
                'name' => 'deleted-'.$user->id,
                'email' => 'deleted-'.$user->id.'@invalid.local',
                'password' => Str::random(64),
                'remember_token' => null,
                'email_verified_at' => null,
                'avatar_url' => null,
                'bio' => null,
                'signature' => null,
                'status' => 'deleted',
                'registration_ip' => null,
                'last_ip' => null,
                'last_seen_at' => null,
                'hide_online' => true,
            ])->save();
            $user->flushRoleCache();

            // The log keeps the account id only, not the old name or email
            ModerationLog::record($admin, 'account_anonymize', $user, null, array_filter(['posts_deleted' => $deletePosts ?: null]));
        });
    }

    // Guild owners leave: the oldest remaining member takes over, an empty guild is closed
    protected function handOver(?Guild $guild, User $user): void
    {
        if (! $guild) {
            return;
        }
        $next = $guild->members()->where('user_id', '!=', $user->id)->orderByRaw("role = 'officer' desc")->orderBy('created_at')->first();
        if ($next) {
            $next->update(['role' => 'owner']);

            return;
        }
        $guild->section?->update(['guild_id' => null, 'visibility' => 'private', 'name' => $guild->name.' ('.__('closed guild').')']);
        $guild->delete();
    }
}
