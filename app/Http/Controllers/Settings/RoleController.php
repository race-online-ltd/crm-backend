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
use Illuminate\Support\Facades\DB;

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



    // public function rolePermission(Role $role)
    // {
    //     // Load role permissions
    //     $rolePermissionIds = $role->rolePermissions()
    //         ->pluck('navigation_permission_id')
    //         ->toArray();

    //     // Load all navigation items with actions
    //     $items = NavigationItem::with([
    //         'children.navigationPermissions.permissionAction',
    //         'navigationPermissions.permissionAction'
    //     ])
    //     ->where('is_active', true)
    //     ->orderBy('sort_order')
    //     ->get();

    //     // Separate groups & standalone
    //     $groups = $items->where('type', 'group')->values();
    //     $standalone = $items->where('type', 'item')->whereNull('parent_id')->values();

    //     return response()->json([
    //         'groups' => $groups->map(fn($group) => $this->formatGroup($group, $rolePermissionIds)),
    //         'standalone' => $standalone->map(fn($item) => $this->formatItem($item, $rolePermissionIds)),
    //     ]);
    // }

    // private function formatGroup($group, $rolePermissionIds)
    // {
    //     return [
    //         'key' => $group->key,
    //         'label' => $group->label,
    //         'items' => $group->children->map(fn($item) => $this->formatItem($item, $rolePermissionIds))
    //     ];
    // }

    // private function formatItem($item, $rolePermissionIds)
    // {
    //     return [
    //         'key' => $item->key,
    //         'label' => $item->label,
    //         'actions' => $item->navigationPermissions->map(function ($perm) use ($rolePermissionIds) {
    //             return [
    //                 'key' => $perm->permissionAction->key,
    //                 'label' => $perm->permissionAction->label,
    //                 'checked' => in_array($perm->navigation_permission_id ?? $perm->id, $rolePermissionIds)
    //             ];
    //         })
    //     ];
    // }

public function rolePermission($roleId)
{
    // ✅ Step 1: get assigned permission ids (RAW QUERY)
    $assignedIds = array_column(
        DB::select("
            SELECT navigation_permission_id
            FROM role_permissions
            WHERE role_id = ?
        ", [$roleId]),
        'navigation_permission_id'
    );

    // ✅ Step 2: get all menus + permissions
    $rows = \DB::select("
        SELECT
            ni.id AS menu_id,
            ni.parent_id,
            ni.key AS menu_key,
            ni.label AS menu_label,
            ni.sort_order,

            np.id AS permission_id,
            pa.key AS action_key,
            pa.label AS action_label

        FROM navigation_items ni

        LEFT JOIN navigation_permissions np
            ON np.navigation_item_id = ni.id

        LEFT JOIN permission_actions pa
            ON pa.id = np.permission_action_id

        WHERE ni.is_active = 1

        ORDER BY ni.parent_id ASC, ni.sort_order ASC
    ");

    // ✅ Step 3: build map
    $map = [];
    $parentMap = [];

    foreach ($rows as $row) {

        $parentMap[$row->menu_id] = $row->parent_id;

        if (!isset($map[$row->menu_id])) {
            $map[$row->menu_id] = [
                'id' => $row->menu_id,
                'key' => $row->menu_key,
                'label' => $row->menu_label,
                'children' => [],
                'permissions' => []
            ];
        }

        // ✅ attach permissions with checked
        if ($row->permission_id) {
            $map[$row->menu_id]['permissions'][$row->permission_id] = [
                'id' => $row->permission_id,
                'key' => $row->action_key,
                'label' => $row->action_label,
                'checked' => in_array($row->permission_id, $assignedIds)
            ];
        }
    }

    // ✅ Step 4: build tree (O(n))
    $menus = [];

    foreach ($map as $id => &$menu) {
        $parentId = $parentMap[$id];

        if ($parentId && isset($map[$parentId])) {
            $map[$parentId]['children'][] = &$menu;
        } else {
            $menus[] = &$menu;
        }
    }

    // ✅ Step 5: clean structure
    $menus = array_map(fn($menu) => $this->formatTree($menu), $menus);

    return response()->json([
        'menus' => $menus
    ]);
}
private function formatTree($menu)
{
    return [
        'id' => $menu['id'],
        'key' => $menu['key'],
        'label' => $menu['label'],
        'permissions' => array_values($menu['permissions']),
        'children' => array_map(fn($child) => $this->formatTree($child), $menu['children'])
    ];
}
   public function updateRolePermissions(Request $request, Role $role)
{
    $permissions = $request->input('permissions');
    if ($permissions === null) {
        $permissions = $request->all();
    }

    if (!is_array($permissions)) {
        return response()->json([
            'message' => 'Permissions must be an array.',
        ], 422);
    }

    $permissionIds = [];

    foreach ($permissions as $permission) {
        if (is_array($permission)) {
            if (isset($permission['items']) && is_array($permission['items'])) {
                foreach ($permission['items'] as $item) {
                    if (!isset($item['actions']) || !is_array($item['actions'])) {
                        continue;
                    }
                    foreach ($item['actions'] as $actionId) {
                        $permissionIds[] = $actionId;
                    }
                }
                continue;
            }

            if (isset($permission['actions']) && is_array($permission['actions'])) {
                foreach ($permission['actions'] as $actionId) {
                    $permissionIds[] = $actionId;
                }
                continue;
            }

            $permissionIds[] = $permission;
            continue;
        }

        $permissionIds[] = $permission;
    }

    // 🔥 Normalize and unique ids
    $permissionIds = array_values(array_unique(array_filter($permissionIds, fn($id) => $id !== null && $id !== '')));

    // Validate IDs exist
    $validIds = NavigationPermission::whereIn('id', $permissionIds)
        ->pluck('id')
        ->toArray();

    // 🔥 Remove old permissions
    RolePermission::where('role_id', $role->id)->delete();

    // 🔥 Insert new permissions
    if (!empty($validIds)) {
        $insertData = array_map(fn($navPermId) => [
            'role_id' => $role->id,
            'navigation_permission_id' => $navPermId,
        ], $validIds);
        RolePermission::insert($insertData);
    }

    return response()->json([
        'message' => 'Permissions updated successfully',
    ]);
}


}
