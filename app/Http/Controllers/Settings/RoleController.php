<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\NavigationItem;
use App\Models\NavigationPermission;
use App\Models\RolePermission;

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



    public function rolePermission(Role $role)
    {
        // Load role permissions
        $rolePermissionIds = $role->rolePermissions()
            ->pluck('navigation_permission_id')
            ->toArray();

        // Load all navigation items with actions
        $items = NavigationItem::with([
            'children.navigationPermissions.permissionAction',
            'navigationPermissions.permissionAction'
        ])
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

        // Separate groups & standalone
        $groups = $items->where('type', 'group')->values();
        $standalone = $items->where('type', 'item')->whereNull('parent_id')->values();

        return response()->json([
            'groups' => $groups->map(fn($group) => $this->formatGroup($group, $rolePermissionIds)),
            'standalone' => $standalone->map(fn($item) => $this->formatItem($item, $rolePermissionIds)),
        ]);
    }

    private function formatGroup($group, $rolePermissionIds)
    {
        return [
            'key' => $group->key,
            'label' => $group->label,
            'items' => $group->children->map(fn($item) => $this->formatItem($item, $rolePermissionIds))
        ];
    }

    private function formatItem($item, $rolePermissionIds)
    {
        return [
            'key' => $item->key,
            'label' => $item->label,
            'actions' => $item->navigationPermissions->map(function ($perm) use ($rolePermissionIds) {
                return [
                    'key' => $perm->permissionAction->key,
                    'label' => $perm->permissionAction->label,
                    'checked' => in_array($perm->navigation_permission_id ?? $perm->id, $rolePermissionIds)
                ];
            })
        ];
    }


   public function updateRolePermissions(Request $request, Role $role)
{
    $request->validate([
        'permissions' => 'required|array',
    ]);

    $permissions = $request->permissions;

    $permissionIds = [];

    foreach ($permissions as $group) {
        if (!isset($group['items'])) continue;

        foreach ($group['items'] as $item) {
            if (!isset($item['actions'])) continue;

            foreach ($item['actions'] as $actionId) {
                $permissionIds[] = $actionId;
            }
        }
    }

    // 🔥 unique ids
    $permissionIds = array_unique($permissionIds);

    // Validate IDs exist
    $validIds = NavigationPermission::whereIn('id', $permissionIds)
        ->pluck('id')
        ->toArray();

    // 🔥 Remove old permissions
    RolePermission::where('role_id', $role->id)->delete();

    // 🔥 Insert new permissions
    $insertData = [];

    foreach ($validIds as $navPermId) {
        $insertData[] = [
            'role_id' => $role->id,
            'navigation_permission_id' => $navPermId,
        ];
    }

    if (!empty($insertData)) {
        RolePermission::insert($insertData);
    }

    return response()->json([
        'message' => 'Permissions updated successfully',
    ]);
}


}
