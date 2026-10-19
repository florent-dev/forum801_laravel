<?php

use App\Models\Post;
use App\Models\Role;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RoleSeeder::class))
    ->in('Feature');

// Create a user holding the given role, optionally scoped to a section
function userWithRole(string $role, ?Section $section = null): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::MEMBER);
    if ($role !== Role::MEMBER) {
        $user->assignRole($role, $section);
    }

    return $user->fresh();
}

// Give a user an active sanction of the given type
function sanction(User $user, string $type = 'suspension', ?string $reason = 'Test', ?\DateTimeInterface $until = null): User
{
    $user->sanctions()->create(['type' => $type, 'reason' => $reason, 'expires_at' => $until ?? now()->addDay()]);
    $user->flushSanctionCache();

    return $user;
}

// Create a thread with a first post in the given section
function threadIn(Section $section, ?User $author = null): Thread
{
    $author ??= User::factory()->create();
    $thread = Thread::factory()->create(['section_id' => $section->id, 'user_id' => $author->id]);
    $post = Post::factory()->create(['thread_id' => $thread->id, 'user_id' => $author->id]);
    $thread->forceFill(['posts_count' => 1, 'last_post_id' => $post->id, 'last_post_user_id' => $author->id])->save();

    return $thread->fresh();
}
