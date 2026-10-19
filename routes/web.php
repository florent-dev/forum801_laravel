<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ForumController::class, 'home'])->name('home');
Route::get('/s/{section:slug}', [ForumController::class, 'section'])->name('sections.show');
Route::get('/p/{post}', [ForumController::class, 'post'])->whereNumber('post')->withTrashed()->name('posts.show');
Route::get('/unread/{thread}', [ForumController::class, 'firstUnread'])->whereNumber('thread')->withTrashed()->name('threads.unread');
Route::get('/t/{thread}/{slug?}', [ForumController::class, 'thread'])->whereNumber('thread')->withTrashed()->name('threads.show');
Route::get('/online', [ForumController::class, 'online'])->name('online');
Route::get('/search', [ForumController::class, 'search'])->middleware('throttle:30,1')->name('search');
Route::get('/u/{user:name}', [ProfileController::class, 'show'])->name('profile');
Route::get('/feed.rss', [FeedController::class, 'latest'])->name('feeds.latest');
Route::get('/s/{section:slug}/feed.rss', [FeedController::class, 'section'])->name('feeds.section');
Route::get('/api/users', [FeedController::class, 'users'])->middleware(['auth', 'throttle:60,1'])->name('api.users');
Route::view('/help', 'forum.help')->name('help');
Route::view('/guilds', 'guilds.index')->name('guilds.index');
Route::get('/g/{guild:slug}', fn (\App\Models\Guild $guild) => view('guilds.show', compact('guild')))->name('guilds.show');
Route::get('/g/{guild:slug}/manage', function (\App\Models\Guild $guild) {
    \Illuminate\Support\Facades\Gate::authorize('manageMembers', $guild);

    return view('guilds.manage', compact('guild'));
})->middleware('auth')->name('guilds.manage');
Route::post('/tour/done/{key}', function (string $key) {
    if ($user = auth()->user()) {
        $user->forceFill(['tours_seen' => array_values(array_unique([...($user->tours_seen ?? []), $key]))])->saveQuietly();
    }

    return response()->noContent();
})->whereIn('key', ['main', 'section', 'thread'])->middleware('throttle:20,1')->name('tour.done');
// Guests keep it in the session, members on their account
Route::post('/language', [ProfileController::class, 'updateLanguage'])->middleware('throttle:30,1')->name('language.update');
// Captcha image for the registration form; every request draws a new code
Route::get('/captcha.png', function (\App\Services\CaptchaService $captcha) {
    return response($captcha->png($captcha->generate()), 200, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
})->middleware('throttle:30,1')->name('captcha');

// Hidden staff access while the forum is under maintenance
Route::get('/staff/{token}', function (string $token, \App\Services\MaintenanceService $maintenance) {
    abort_unless($maintenance->checkSecret($token), 404);
    session(['maintenance_access' => $token]);
    app(\App\Services\SecurityService::class)->record('maintenance_access', auth()->user());

    return redirect()->route(auth()->check() ? 'home' : 'login');
})->middleware('throttle:10,1')->name('maintenance.access');
Route::post('/community', [CommunityController::class, 'update'])->middleware('throttle:30,1')->name('community.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerificationController::class, 'send'])->middleware('throttle:3,1')->name('verification.send');
    Route::get('/s/{section:slug}/new', [ForumController::class, 'createThread'])->name('threads.create');
    Route::get('/unread', [ForumController::class, 'unread'])->name('unread');
    Route::get('/notifications', [ForumController::class, 'notifications'])->name('notifications');
    Route::get('/notifications/{id}', [ForumController::class, 'openNotification'])->name('notifications.open');
    Route::view('/friends', 'forum.friends')->name('friends');
    Route::get('/bookmarks', [ForumController::class, 'bookmarks'])->name('bookmarks');
    Route::put('/settings/privacy', [ProfileController::class, 'updatePrivacy'])->name('settings.privacy');
    Route::get('/subscriptions', [ForumController::class, 'subscriptions'])->name('subscriptions');
    Route::get('/messages', [ForumController::class, 'messages'])->name('messages');
    Route::get('/messages/{conversation}', [ForumController::class, 'conversation'])->whereNumber('conversation')->name('messages.show');
    Route::get('/settings/export', [\App\Http\Controllers\DataExportController::class, 'mine'])->name('settings.export');
    Route::get('/settings/{tab?}', [ProfileController::class, 'edit'])->whereIn('tab', ['profile', 'language', 'privacy', 'account'])->name('settings');
    Route::put('/settings', [ProfileController::class, 'update'])->name('settings.update');
    Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('settings.password');
    Route::put('/settings/email', [ProfileController::class, 'updateEmail'])->middleware('throttle:6,1')->name('settings.email');
    Route::get('/moderation', [ForumController::class, 'moderation'])->middleware('can:access-moderation')->name('moderation');
    Route::get('/moderation/users/{user}', fn (\App\Models\User $user) => view('moderation.user', compact('user')))->whereNumber('user')->middleware('can:access-moderation')->name('moderation.users.show');
    Route::get('/moderation/reports/{report}', [ForumController::class, 'report'])->whereNumber('report')->middleware('can:access-moderation')->name('moderation.reports.show');

    Route::middleware('role:administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::view('/users', 'admin.users')->name('users');
        Route::view('/sections', 'admin.sections')->name('sections');
        Route::view('/roles', 'admin.roles')->name('roles');
        Route::view('/security', 'admin.security')->name('security');
        Route::view('/maintenance', 'admin.maintenance')->name('maintenance');
        Route::view('/settings', 'admin.forum-settings')->name('settings');
        Route::view('/word-filter', 'admin.word-filters')->name('words');
        Route::view('/health', 'admin.health')->name('health');
        Route::view('/personal-data', 'admin.personal-data')->name('privacy');
        Route::get('/personal-data/{user}/export', [\App\Http\Controllers\DataExportController::class, 'user'])->name('privacy.export');
    });
});

// Section owners manage their own section from here
Route::get('/s/{section:slug}/settings', [ForumController::class, 'sectionSettings'])->middleware('auth')->name('sections.settings');
