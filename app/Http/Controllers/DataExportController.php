<?php

namespace App\Http\Controllers;

use App\Models\ModerationLog;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class DataExportController extends Controller
{
    // A user downloads their own data from Settings › Account
    public function mine(Request $request, PrivacyService $privacy)
    {
        $key = 'data-export:'.$request->user()->id;
        abort_if(RateLimiter::tooManyAttempts($key, 3), 429, __('Too many exports. Try again later.'));
        RateLimiter::hit($key, 3600);

        return $this->download($request->user(), $privacy);
    }

    public function user(Request $request, User $user, PrivacyService $privacy)
    {
        ModerationLog::record($request->user(), 'data_export', $user);

        return $this->download($user, $privacy);
    }

    protected function download(User $user, PrivacyService $privacy)
    {
        $json = json_encode($privacy->export($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.config('app.name').'-data-'.$user->id.'-'.now()->format('Ymd').'.json"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
