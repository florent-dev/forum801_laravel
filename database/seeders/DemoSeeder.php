<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Report;
use App\Models\Role;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumService;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\RateLimiter;

class DemoSeeder extends Seeder
{
    public function run(ForumService $forum): void
    {
        $users = [
            'admin' => Role::ADMINISTRATOR,
            'senior' => Role::SENIOR_MODERATOR,
            'modo' => Role::MODERATOR,
            'privatemod' => Role::PRIVATE_MODERATOR,
            'owner' => Role::SECTION_OWNER,
            'alice' => Role::MEMBER,
            'bob' => Role::MEMBER,
            'charlie' => Role::MEMBER,
        ];
        $discussions = Section::where('slug', 'discussions')->first();
        $bugs = Section::where('slug', 'bugs')->first();

        foreach ($users as $name => $role) {
            $user = User::updateOrCreate(['name' => $name], [
                'email' => "{$name}@example.com",
                'password' => 'password',
                'bio' => "Hello, I am {$name}.",
            ]);
            $user->email_verified_at ??= now();
            $user->save();
            if ($user->roles()->count() === 0) {
                $user->assignRole(Role::MEMBER);
                if ($role !== Role::MEMBER) {
                    $user->assignRole($role, in_array($role, Role::SECTION_SCOPED, true) ? ($role === Role::SECTION_OWNER ? $bugs : $discussions) : null);
                }
            }
        }

        if ($discussions->threads()->exists()) {
            return;
        }

        $all = User::all()->keyBy('name');
        $sections = Section::where('visibility', 'public')->get();

        $rules = $forum->createThread($all['admin'], Section::where('slug', 'announcements')->first(), 'General Forum Guidelines', <<<'MD'
## 1. Be respectful
Treat others the way you would like to be treated.

## 2. Post in the right section
Use **Bugs** for problems and **Suggestions** for ideas.

## 3. No spam or double posts
Use the *edit* button instead of posting twice.

> Moderators can lock, move or hide content that breaks these rules.
MD);
        $rules->forceFill(['is_pinned' => true, 'is_locked' => true])->save();

        // French rules for the French community
        $regles = $forum->createThread($all['admin'], Section::where('slug', 'fr-annonces')->first(), 'Règles générales du forum', <<<'MD'
## 1. Soyez respectueux
Traitez les autres comme vous aimeriez être traité.

## 2. Postez dans la bonne section
Utilisez **Bugs** pour les problèmes et **Suggestions** pour les idées.

## 3. Pas de spam ni de double post
Utilisez le bouton *modifier* au lieu de poster deux fois.

> Les modérateurs peuvent verrouiller, déplacer ou masquer les contenus qui enfreignent ces règles.
MD);
        $regles->forceFill(['is_pinned' => true, 'is_locked' => true])->save();

        foreach ($sections as $section) {
            foreach (range(1, rand(4, 12)) as $i) {
                $author = $all->random();
                $thread = $forum->createThread($author, $section, $this->title($section->community), $this->body($section->community));
                foreach (range(0, rand(0, 25)) as $j) {
                    RateLimiter::clear('posting:'.$author->id);
                    $replier = $all->random();
                    RateLimiter::clear('posting:'.$replier->id);
                    $forum->reply($replier, $thread, $this->body($section->community));
                }
                RateLimiter::clear('posting:'.$author->id);
            }
        }
        // Clear all posting limits left by the seeding run
        $all->each(fn ($u) => RateLimiter::clear('posting:'.$u->id));

        $this->seedBulk($all, $forum);
        $this->spreadDates();
        $this->seedReports($all);

        // Keep only a handful of recent notifications so demo inboxes stay readable
        DB::table('notifications')->whereNotIn('id', DB::table('notifications')->latest()->limit(40)->pluck('id'))->delete();
        $this->seedConversation($all);
        $this->seedGuild($all, $forum);
    }

    // A demo guild with a few members, an application and some forum activity
    protected function seedGuild($users, ForumService $forum): void
    {
        $guilds = app(\App\Services\GuildService::class);
        $guild = $guilds->create($users['alice'], [
            'name' => 'Cheese Knights', 'tag' => 'CHK', 'community' => 'en', 'recruitment' => 'application',
            'description' => "## Welcome to the Cheese Knights! 🧀\n\nWe meet every evening and help newcomers.\n\n- Be kind\n- Share your cheese\n- Have fun",
        ]);
        $guild->members()->create(['user_id' => $users['bob']->id, 'role' => 'officer']);
        $guild->members()->create(['user_id' => $users['modo']->id, 'role' => 'member']);
        $guild->applications()->create(['user_id' => $users['charlie']->id, 'message' => 'Hi! I play every day and I love cheese. Can I join?']);
        foreach (['Weekly event schedule', 'Share your best maps'] as $title) {
            RateLimiter::clear('posting:'.$users['alice']->id);
            $forum->createThread($users['alice'], $guild->section, $title, $this->body('en'));
        }
        $users->each(fn ($u) => RateLimiter::clear('posting:'.$u->id));
    }

    // Volume data: a section with several pages of threads and a thread with several pages of posts
    protected function seedBulk($users, ForumService $forum): void
    {
        $renderer = app(\App\Services\MarkupRenderer::class);
        $discussions = Section::where('slug', 'discussions')->first();
        $ids = $users->pluck('id')->values();
        $now = now();

        foreach (range(1, 90) as $i) {
            $thread = Thread::create([
                'section_id' => $discussions->id,
                'user_id' => $ids->random(),
                'title' => $this->title('en'),
                'slug' => 'thread-'.$i,
                'last_post_at' => $now,
            ]);
            $thread->forceFill(['slug' => \Illuminate\Support\Str::slug($thread->title)])->saveQuietly();
            $rows = [];
            foreach (range(1, rand(1, 12)) as $j) {
                $body = $this->body('en');
                $rows[] = ['thread_id' => $thread->id, 'user_id' => $j === 1 ? $thread->user_id : $ids->random(), 'body' => $body, 'body_html' => $renderer->render($body), 'created_at' => $now, 'updated_at' => $now];
            }
            Post::insert($rows);
            $thread->refreshStats();
        }

        // One long thread to exercise post pagination and the new-posts banner
        $mega = $forum->createThread($users['alice'], $discussions, 'Introduce yourself! (the big welcome thread)', 'Say hi and tell us how long you have been playing 🐭');
        $rows = [];
        foreach (range(1, 80) as $j) {
            $body = fake()->randomElement(['Hi everyone! ', 'Hello! ', 'Hey there, ']).$this->faker('en')->realText(rand(60, 160));
            $rows[] = ['thread_id' => $mega->id, 'user_id' => $ids->random(), 'body' => $body, 'body_html' => $renderer->render($body), 'created_at' => $now, 'updated_at' => $now];
        }
        Post::insert($rows);
        $mega->refreshStats();
        $mega->forceFill(['is_pinned' => true])->saveQuietly();

        // A few French titles so the French community feels real
        $titles = ['Présentez-vous ici !', 'Quelle est votre carte préférée ?', 'Bug : le fromage disparaît', 'Idée : un mode coopératif', 'Recrutement pour la tribu des Souris Volantes', 'Vos meilleurs souvenirs de chamane'];
        foreach (Section::where('community', 'fr')->where('visibility', 'public')->get() as $k => $section) {
            $forum->createThread($users->random(), $section, $titles[$k % count($titles)], 'Salut tout le monde ! Qu’en pensez-vous ? 🧀');
            $users->each(fn ($u) => RateLimiter::clear('posting:'.$u->id));
        }
    }

    // Text generators speak the language of the community
    protected function faker(string $community): \Faker\Generator
    {
        static $fakers = [];
        $locale = ['fr' => 'fr_FR', 'es' => 'es_ES', 'de' => 'de_DE', 'br' => 'pt_BR', 'it' => 'it_IT', 'nl' => 'nl_NL', 'pl' => 'pl_PL', 'ru' => 'ru_RU', 'tr' => 'tr_TR', 'hu' => 'hu_HU', 'ro' => 'ro_RO'][$community] ?? 'en_US';

        return $fakers[$locale] ??= \Faker\Factory::create($locale);
    }

    protected function title(string $community): string
    {
        $text = rtrim($this->faker($community)->realText(rand(30, 70)), '.!?;:, ');

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    protected function body(string $community): string
    {
        return collect(range(1, rand(1, 3)))->map(fn () => $this->faker($community)->realText(rand(120, 420)))->implode("\n\n");
    }

    protected function seedConversation($users): void
    {
        $messages = app(MessageService::class);
        $conversation = $messages->start($users['alice'], collect([$users['bob'], $users['modo']]), 'Tribe house party tonight?', 'Hey! Who is up for a tribe house party at 9pm? 🧀');
        RateLimiter::clear('messaging:'.$users['bob']->id);
        $messages->reply($users['bob'], $conversation, 'Count me in! @modo are you coming?');
        $messages->reply($users['modo'], $conversation, 'Sure, I will bring the cheese.');
        foreach ($users as $u) {
            RateLimiter::clear('messaging:'.$u->id);
        }
    }

    // Spread demo activity over the last 60 days so lists look realistic
    protected function spreadDates(): void
    {
        foreach (Thread::with('posts')->get() as $thread) {
            $cursor = now()->subMinutes(rand(60, 60 * 24 * 60));
            foreach ($thread->posts->sortBy('id') as $post) {
                $post->forceFill(['created_at' => $cursor, 'updated_at' => $cursor])->saveQuietly();
                $cursor = $cursor->copy()->addMinutes(rand(5, 600));
                if ($cursor->isFuture()) {
                    $cursor = now()->subMinutes(rand(1, 30));
                }
            }
            $thread->forceFill(['created_at' => $thread->posts->min('created_at')])->saveQuietly();
            $thread->refreshStats();
            $thread->forceFill(['updated_at' => $thread->last_post_at])->saveQuietly();
        }
    }

    protected function seedReports($users): void
    {
        $posts = Post::inRandomOrder()->limit(6)->get();
        foreach ($posts as $post) {
            $reporter = $users->where('id', '!=', $post->user_id)->random();
            Report::create(['post_id' => $post->id, 'reporter_id' => $reporter->id, 'reason' => fake()->randomElement(['Spam', 'Insulting another user', 'Off topic', 'Double post', 'Necro bump'])]);
        }
    }
}
