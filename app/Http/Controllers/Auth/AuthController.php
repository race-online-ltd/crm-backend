<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $login = $validated['login'];
        $password = $validated['password'];

        $user = User::query()
            ->with('role:id,name')
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

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => $this->transformUser(auth('api')->user() ?? $user),
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

    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        return response()->json([
            'message' => 'User fetched successfully.',
            'data' => [
                'user' => $this->transformUser($user),
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
