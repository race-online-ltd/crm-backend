<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSystemUserRequest;
use App\Http\Requests\Settings\UpdateSystemUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SystemUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('role')
            ->latest()
            ->get()
            ->map(fn (User $user) => $this->transformUser($user))
            ->values();

        return response()->json([
            'message' => 'System users fetched successfully.',
            'data' => $users,
        ]);
    }

    public function store(StoreSystemUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'full_name' => $validated['full_name'],
            'user_name' => $validated['user_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'role_id' => $validated['role_id'],
            'status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'message' => 'System user created successfully.',
            'data' => $this->transformUser($user->fresh()->load('role')),
        ], 201);
    }

    public function update(UpdateSystemUserRequest $request, User $systemUser): JsonResponse
    {
        $validated = $request->validated();

        $payload = [
            'full_name' => $validated['full_name'],
            'user_name' => $validated['user_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role_id' => $validated['role_id'],
            'status' => $validated['status'] ?? $systemUser->status,
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $systemUser->update($payload);

        return response()->json([
            'message' => 'System user updated successfully.',
            'data' => $this->transformUser($systemUser->fresh()->load('role')),
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
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
