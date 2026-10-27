<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Allows the request only for users with the given role, e.g. RequireRole::class.':owner'. */
class RequireRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role !== $role) {
            abort(403, 'Only the owner can open this page.');
        }

        return $next($request);
    }
}