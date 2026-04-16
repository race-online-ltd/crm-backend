<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ListRolePermissionsRequest;
use App\Http\Requests\Settings\ListSystemUsersRequest;
use App\Models\NavigationItem;
use App\Models\RolePermission;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\StoreSystemUserRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Http\Requests\Settings\UpdateSystemUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function rolesIndex(): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->transformRole($role))
            ->values();

        return response()->json([
            'message' => 'Roles fetched successfully.',
            'data' => $roles,
        ]);
    }

    public function storeRole(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => 'Role created successfully.',
            'data' => $this->transformRole($role),
        ], 201);
    }

    public function updateRole(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();

        $role->update([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => 'Role updated successfully.',
            'data' => $this->transformRole($role->fresh()),
        ]);
    }

    public function destroyRole(Role $role): JsonResponse
    {
        try {
            $role->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'This role cannot be deleted because it is assigned to one or more users.',
            ], 422);
        }

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    public function usersIndex(ListSystemUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $roleId = $validated['role_id'] ?? null;
        $perPage = $validated['per_page'] ?? 10;

        $users = User::query()
            ->with('role')
            ->when($search, function (Builder $query, string $searchTerm): void {
                $query->where(function (Builder $innerQuery) use ($searchTerm): void {
                    $innerQuery
                        ->where('full_name', 'like', "%{$searchTerm}%")
                        ->orWhere('user_name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%");
                });
            })
            ->when($roleId, fn (Builder $query, int $selectedRoleId) => $query->where('role_id', $selectedRoleId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'System users fetched successfully.',
            'data' => $users->getCollection()
                ->map(fn (User $user) => $this->transformUser($user))
                ->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        ]);
    }

    public function accessControlIndex(ListRolePermissionsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $roleId = $validated['role_id'] ?? null;

        $roles = Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->transformRole($role))
            ->values();

        $selectedRole = $roleId ? Role::query()->find($roleId) : null;

        $selectedPermissionIds = $roleId
            ? RolePermission::query()
                ->where('role_id', $roleId)
                ->pluck('navigation_permission_id')
                ->all()
            : [];

        $navigationItems = NavigationItem::query()
            ->with([
                'parent:id,label',
                'navigationPermissions.permissionAction:id,key,label',
            ])
            ->when($search, function (Builder $query, string $searchTerm): void {
                $query->where(function (Builder $innerQuery) use ($searchTerm): void {
                    $innerQuery
                        ->where('label', 'like', "%{$searchTerm}%")
                        ->orWhere('key', 'like', "%{$searchTerm}%")
                        ->orWhere('route', 'like', "%{$searchTerm}%")
                        ->orWhereHas('parent', function (Builder $parentQuery) use ($searchTerm): void {
                            $parentQuery->where('label', 'like', "%{$searchTerm}%");
                        })
                        ->orWhereHas('navigationPermissions.permissionAction', function (Builder $permissionQuery) use ($searchTerm): void {
                            $permissionQuery
                                ->where('label', 'like', "%{$searchTerm}%")
                                ->orWhere('key', 'like', "%{$searchTerm}%");
                        });
                });
            })
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (NavigationItem $item) => $this->transformNavigationItem($item, $selectedPermissionIds))
            ->values();

        return response()->json([
            'message' => 'Access control data fetched successfully.',
            'data' => [
                'roles' => $roles,
                'selected_role' => $selectedRole ? $this->transformRole($selectedRole) : null,
                'filters' => [
                    'role_id' => $roleId,
                    'search' => $search,
                ],
                'navigation_items' => $navigationItems,
            ],
        ]);
    }

    public function storeUser(StoreSystemUserRequest $request): JsonResponse
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

    public function updateUser(UpdateSystemUserRequest $request, User $systemUser): JsonResponse
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

    public function destroyUser(User $systemUser): JsonResponse
    {
        $systemUser->delete();

        return response()->json([
            'message' => 'System user deleted successfully.',
        ]);
    }

    private function transformRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->name,
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
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

    private function transformNavigationItem(NavigationItem $item, array $selectedPermissionIds): array
    {
        return [
            'id' => $item->id,
            'parent_id' => $item->parent_id,
            'parent_label' => $item->parent?->label,
            'key' => $item->key,
            'label' => $item->label,
            'route' => $item->route,
            'type' => $item->type,
            'icon' => $item->icon,
            'sort_order' => $item->sort_order,
            'is_active' => (bool) $item->is_active,
            'action_permissions' => $item->navigationPermissions
                ->sortBy(fn ($permission) => $permission->permissionAction?->label)
                ->map(fn ($permission) => [
                    'navigation_permission_id' => $permission->id,
                    'permission_action_id' => $permission->permission_action_id,
                    'action_key' => $permission->permissionAction?->key,
                    'action_label' => $permission->permissionAction?->label,
                    'checked' => in_array($permission->id, $selectedPermissionIds, true),
                ])
                ->values(),
        ];
    }
}
