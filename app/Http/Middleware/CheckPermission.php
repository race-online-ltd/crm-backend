<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $permission = $request->route()->defaults['permission'] ?? null;

    if (!$permission) {
        return $next($request);
    }

    $user = auth('api')->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // ✅ ALWAYS DB থেকে fresh permission নাও
    $permissions = $user->getPermissionKeys();

    if (!in_array($permission, $permissions)) {
        return response()->json([
            'message' => 'Forbidden',
            'required_permission' => $permission
        ], 403);
    }

    return $next($request);
    }
}
