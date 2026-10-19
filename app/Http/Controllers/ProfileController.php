<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, User $user)
    {
        $tab = in_array($request->query('tab'), ['threads', 'friends'], true) ? $request->query('tab') : 'posts';
        $visible = Section::visibleTo($request->user())->select('id');
        $threads = $tab === 'threads'
            ? $user->threads()->with('section')->whereIn('section_id', $visible)->latest('id')->paginate(15)->withQueryString()
            : null;
        $posts = Post::with(['thread.section', 'moderator:id,name'])
            ->where('user_id', $user->id)
            ->where('is_hidden', false)
            ->whereHas('thread', fn ($t) => $t->whereIn('section_id', Section::visibleTo($request->user())->select('id')))
            ->latest('id')
            ->paginate(15)->withQueryString();

        $roles = $user->roleAssignments();
        $friendIds = $user->friendIds();
        $postCount = $user->posts()->where('is_hidden', false)->count();
        $scopedSections = Section::whereIn('id', $roles->pluck('pivot.scope_id')->filter())->pluck('name', 'id');

        return view('forum.profile', [
            'profileUser' => $user,
            'posts' => $posts,
            'threads' => $threads,
            'tab' => $tab,
            'reactionsReceived' => \App\Models\Reaction::whereIn('post_id', $user->posts()->select('id'))->count(),
            'roles' => $roles,
            'scopedSections' => $scopedSections,
            'postCount' => $postCount,
            'rank' => User::rankFor($postCount),
            'friendCount' => count($friendIds),
            'friends' => User::with('roles')->whereIn('id', $friendIds)->orderByDesc('last_seen_at')->limit($tab === 'friends' ? 200 : 12)->get(),
            'solutions' => \App\Models\Thread::whereIn('solution_post_id', $user->posts()->select('id'))->count(),
            'threadCount' => $user->threads()->count(),
        ]);
    }

    public function edit(Request $request, string $tab = 'profile')
    {
        return view('forum.settings', ['user' => $request->user(), 'tab' => $tab]);
    }

    public function updateLanguage(Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'in:'.implode(',', array_keys(\App\Support\Locale::all()))],
        ]);
        \App\Support\Locale::set($data['locale']);
        app()->setLocale($data['locale']);

        return redirect()->back()->with('toast', __('Language: :name', ['name' => \App\Support\Locale::label($data['locale'])]));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $this->authorize('update', $user);
        $data = $request->validate([
            'avatar_url' => ['nullable', 'url:https', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'signature' => ['nullable', 'string', 'max:300'],
        ]);
        $user->update($data);

        return redirect()->route('settings', 'profile')->with('toast', __('Profile saved.'));
    }

    public function updatePrivacy(Request $request)
    {
        $data = $request->validate([
            'mention_policy' => ['required', 'in:'.implode(',', \App\Models\User::MENTION_POLICIES)],
        ]);
        $request->user()->update(['hide_online' => $request->boolean('hide_online'), 'mention_policy' => $data['mention_policy']]);

        return redirect()->route('settings', 'privacy')->with('toast', __('Privacy settings saved.'));
    }

    public function updatePassword(Request $request)
    {
        // Separate error bags keep the two account forms from showing each other's errors
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);
        $request->user()->update(['password' => $data['password']]);
        app(\App\Services\SecurityService::class)->record('password_changed', $request->user());

        return redirect()->route('settings', 'account')->with('toast', __('Password changed.'));
    }

    public function updateEmail(Request $request)
    {
        $data = $request->validateWithBag('email', [
            'current_password' => ['required', 'current_password'],
            'email' => ['required', 'email:rfc,filter', 'not_regex:/[\r\n]/', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($request->user()->id)],
        ]);
        $user = $request->user();
        if ($user->email !== $data['email']) {
            // A new address must be confirmed again before posting
            app(\App\Services\SecurityService::class)->record('email_changed', $user, ['from' => $user->email, 'to' => $data['email']]);
            $user->forceFill(['email' => $data['email'], 'email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('settings', 'account')->with('toast', __('Email updated.'));
    }
}
