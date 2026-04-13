<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->latest()
            ->get()
            ->map(fn (User $user) => $this->transformUser($user))
            ->values();

        return response()->json([
            'message' => 'System users fetched successfully.',
            'data' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $user = User::create([
            'full_name' => trim($validated['full_name']),
            'user_name' => trim($validated['user_name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => trim($validated['phone']),
            'password' => $validated['password'],
            'role_id' => $validated['role_id'],
            'status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'message' => 'System user created successfully.',
            'data' => $this->transformUser($user->fresh()),
        ], 201);
    }

    public function update(Request $request, User $systemUser): JsonResponse
    {
        $validated = $request->validate($this->rules($systemUser->id, true));

        $payload = [
            'full_name' => trim($validated['full_name']),
            'user_name' => trim($validated['user_name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => trim($validated['phone']),
            'role_id' => $validated['role_id'],
            'status' => $validated['status'] ?? $systemUser->status,
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $systemUser->update($payload);

        return response()->json([
            'message' => 'System user updated successfully.',
            'data' => $this->transformUser($systemUser->fresh()),
        ]);
    }

    private function rules(?int $userId = null, bool $isUpdate = false): array
    {
        $passwordRules = $isUpdate
            ? ['nullable', 'string', 'min:6', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/\d/', 'regex:/[^A-Za-z0-9]/']
            : ['required', 'string', 'min:6', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/\d/', 'regex:/[^A-Za-z0-9]/'];

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255', Rule::unique('users', 'user_name')->ignore($userId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['required', 'string', 'min:10', 'max:13', 'regex:/^\d+$/'],
            'password' => $passwordRules,
            'role_id' => ['required', 'integer', Rule::exists('role_table', 'id')],
            'status' => ['sometimes', 'boolean'],
        ];
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
