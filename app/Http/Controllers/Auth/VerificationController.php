<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function notice(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('home')
            : view('auth.verify-email');
    }

    // Signed link from the confirmation email
    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('home')->with('toast', __('Thanks, your email address is confirmed!'));
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }
        $request->user()->sendEmailVerificationNotification();

        return back()->with('toast', __('A new confirmation link has been sent to :email.', ['email' => $request->user()->email]));
    }
}
