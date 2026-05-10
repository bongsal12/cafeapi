<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => "Unauthorized. This action requires role: {$roles}.",
            ], 403);
        }

        // Admin can access everything
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        // Accept comma or pipe separated role lists
        $allowed = preg_split('/[\\,|\\|]/', $roles);
        $allowed = array_map('trim', $allowed);

        if (!in_array($user->role, $allowed, true)) {
            return response()->json([
                'message' => "Unauthorized. This action requires one of roles: " . implode(', ', $allowed) . ".",
            ], 403);
        }

        return $next($request);
    }
}
