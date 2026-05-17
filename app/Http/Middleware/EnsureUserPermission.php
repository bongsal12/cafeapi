<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        $allowed = preg_split('/[\,|\|]/', $permissions) ?: [];
        $allowed = array_values(array_filter(array_map('trim', $allowed)));

        if (!$user->hasAnyPermission($allowed)) {
            return response()->json([
                'message' => 'Unauthorized. This action requires one of permissions: ' . implode(', ', $allowed) . '.',
            ], 403);
        }

        return $next($request);
    }
}
