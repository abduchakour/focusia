<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Simple enough for students on a phone, but not trivial.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Per-IP ceiling on authentication forms, on top of the per e-mail + IP
        // limit in LoginRequest (stops one machine from trying many accounts).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Access codes are short: limit guesses per student.
        RateLimiter::for('join-group', fn (Request $request) => Limit::perMinute(10)->by('join:'.$request->user()?->id));
    }
}
