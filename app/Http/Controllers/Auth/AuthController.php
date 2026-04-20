<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

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
        SELECT
            u.id AS user_id,
            u.user_name,
            u.email,
            r.id AS role_id,
            r.name AS role_name,
            pa.id AS permission_id,
            pa.label AS permission_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN role_permissions rp ON rp.role_id = r.id
        LEFT JOIN permission_actions pa ON pa.id = rp.navigation_permission_id
        WHERE u.id = ?
    ", [$userId]);

    // ❌ যদি user না থাকে
    if (empty($rows)) {
        return response()->json([
            'message' => 'User not found'
        ], 404);
    }

    // ✅ Main response build
    $rolePermissions = [
        'user' => [
            'id' => $rows[0]->user_id,
            'name' => $rows[0]->user_name,
            'email' => $rows[0]->email,
        ],
        'role' => [
            'id' => $rows[0]->role_id,
            'name' => $rows[0]->role_name,
        ],
        'permissions' => collect($rows)
            ->pluck('permission_name')
            ->filter() // null remove
            ->unique()
            ->values()
    ];
        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => $this->transformUser((auth('api')->user() ?? $user)->load('role')),
                'permissions' => $rolePermissions,
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
