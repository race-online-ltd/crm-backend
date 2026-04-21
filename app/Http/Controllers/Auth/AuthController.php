<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $login = $validated['login'];
        $password = $validated['password'];

        $user = User::query()
            ->where('email', $login)
            ->orWhere('user_name', $login)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (!$user->status) {
            return response()->json([
                'message' => 'Your account is inactive.',
            ], 403);
        }

        $attemptFields = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? ['email', 'user_name']
            : ['user_name', 'email'];

        $token = null;

        foreach ($attemptFields as $field) {
            $token = auth('api')->attempt([
                $field => $login,
                'password' => $password,
            ]);

            if ($token) {
                break;
            }
        }

        if (!$token) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }


    $rows = DB::select("
    SELECT pa.key AS permission_key
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    LEFT JOIN role_permissions rp ON rp.role_id = r.id
    LEFT JOIN permission_actions pa ON pa.id = rp.navigation_permission_id
    WHERE u.id = ?
", [$user->id]);

// flat keys
$permissionKeys = collect($rows)
    ->pluck('permission_key')
    ->filter()
    ->unique()
    ->values()
    ->toArray();

// group for response
$groupedPermissions = collect($permissionKeys)
    ->map(function ($key) {
        [$module, $action] = explode('.', $key);
        return compact('module', 'action');
    })
    ->groupBy('module')
    ->map(fn($items) => $items->pluck('action')->unique()->values())
    ->toArray();

// attach raw permissions to user (for middleware)
$user->permissions = $permissionKeys;

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => $this->transformUser((auth('api')->user() ?? $user)->load('role')),
                'permissions' => $groupedPermissions,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
        } catch (JWTException $exception) {
            return response()->json([
                'message' => 'Token could not be refreshed.',
            ], 401);
        }

        $user = JWTAuth::setToken($newToken)->toUser();

        if (!$user || !$user->status) {
            return response()->json([
                'message' => 'Your account is inactive.',
            ], 403);
        }

        return response()->json([
            'message' => 'Token refreshed successfully.',
            'data' => [
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => $this->transformUser($user->load('role')),
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        return response()->json([
            'message' => 'User fetched successfully.',
            'data' => [
                'user' => $this->transformUser($user->load('role')),
            ],
        ]);
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'user_name' => $user->user_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role_id' => $user->role_id,
            'role_name' => $user->role?->name,
            'status' => (bool) $user->status,
        ];
    }
}
