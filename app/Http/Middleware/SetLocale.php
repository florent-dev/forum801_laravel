<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    // The interface language is the visitor's own setting, whatever community they browse
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Locale::current());

        return $next($request);
    }
}
