<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Models\NavigationItem;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->name,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ])
            ->values();

        return response()->json([
            'message' => 'Roles fetched successfully.',
            'data' => $roles,
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => 'Role created successfully.',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->name,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ],
        ], 201);
    }

    public function rolePermission(Role $role): JsonResponse
    {
        $rolePermissionIds = $role->rolePermissions()
            ->pluck('navigation_permission_id')
            ->toArray();

        $items = NavigationItem::with([
            'children.navigationPermissions.permissionAction',
            'navigationPermissions.permissionAction',
        ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $groups = $items->where('type', 'group')->values();
        $standalone = $items->where('type', 'item')->whereNull('parent_id')->values();

        return response()->json([
            'groups' => $groups->map(fn ($group) => $this->formatGroup($group, $rolePermissionIds))->values(),
            'standalone' => $standalone->map(fn ($item) => $this->formatItem($item, $rolePermissionIds))->values(),
        ]);
    }

    private function formatGroup(NavigationItem $group, array $rolePermissionIds): array
    {
        return [
            'key' => $group->key,
            'label' => $group->label,
            'items' => $group->children
                ->sortBy('sort_order')
                ->map(fn ($item) => $this->formatItem($item, $rolePermissionIds))
                ->values(),
        ];
    }

    private function formatItem(NavigationItem $item, array $rolePermissionIds): array
    {
        return [
            'key' => $item->key,
            'label' => $item->label,
            'actions' => $item->navigationPermissions
                ->map(function ($permission) use ($rolePermissionIds): array {
                    return [
                        'key' => $permission->permissionAction->key,
                        'label' => $permission->permissionAction->label,
                        'checked' => in_array($permission->id, $rolePermissionIds, true),
                    ];
                })
                ->values(),
        ];
    }
}
