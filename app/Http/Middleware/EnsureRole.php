<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to one role: ->middleware('role:teacher') or ('role:student').
 * Checked on the server for every request, whatever the UI shows.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $expected = Role::from($role);

        if ($request->user()?->role !== $expected) {
            abort(403, 'Cette page est réservée aux '.($expected === Role::Teacher ? 'enseignants' : 'étudiants').'.');
        }

        return $next($request);
    }
}
