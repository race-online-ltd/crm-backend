<?php

namespace App\Http\Controllers;

use App\Models\EntityColumnMapping;
use App\Models\FeatureActionPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NavigationItemController extends Controller
{
    public function getActiveItems()
    {
        $items = DB::table('navigation_items')
            ->select('id', 'label')
            ->where('is_active', 1)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }


    public function getByNavigationId($navigation_id)
    {
        $features = DB::table('navigation_feature_permissions')
            ->select('id', 'feature_name')
            ->where('navigation_id', $navigation_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $features
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'role_id'       => 'required|integer',
            'navigation_id' => 'required|integer',
            'feature_id'    => 'required|integer',
            'read'          => 'nullable|boolean',
            'write'         => 'nullable|boolean',
            'modify'        => 'nullable|boolean',
            'delete'        => 'nullable|boolean',
        ]);

        
        $exists = DB::table('user_view_permissions')
            ->where('role_id', $request->role_id)
            ->where('navigation_id', $request->navigation_id)
            ->where('feature_id', $request->feature_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This permission already exists'
            ], 409);
        }

        
        $permission = DB::table('user_view_permissions')->insertGetId([
            'role_id'       => $request->role_id,
            'navigation_id' => $request->navigation_id,
            'feature_id'    => $request->feature_id,
            'read'          => $request->read ?? false,
            'write'         => $request->write ?? false,
            'modify'        => $request->modify ?? false,
            'delete'        => $request->delete ?? false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'id'      => $permission
        ]);
    }



    



    public function show(Request $request)
    {
        $request->validate([
            'role_id'       => 'required|integer',
            'navigation_id' => 'required|integer',
        ]);

        $data = DB::table('user_view_permissions')
            ->where('role_id', $request->role_id)
            ->where('navigation_id', $request->navigation_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }



    public function update(Request $request)
    {
        $request->validate([
            'role_id'       => 'required|integer',
            'navigation_id' => 'required|integer',
            'permissions'   => 'required|array',
            'permissions.*.feature_id' => 'required|integer',
            'permissions.*.read'   => 'nullable|boolean',
            'permissions.*.write'  => 'nullable|boolean',
            'permissions.*.modify' => 'nullable|boolean',
            'permissions.*.delete' => 'nullable|boolean',
        ]);

        $role_id = $request->role_id;
        $navigation_id = $request->navigation_id;

        foreach ($request->permissions as $item) {
            DB::table('user_view_permissions')->updateOrInsert(
                [
                    'role_id'       => $role_id,
                    'navigation_id' => $navigation_id,
                    'feature_id'    => $item['feature_id'],
                ],
                [
                    'read'       => $item['read'] ?? false,
                    'write'      => $item['write'] ?? false,
                    'modify'     => $item['modify'] ?? false,
                    'delete'     => $item['delete'] ?? false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully'
        ]);
    }


    public function storeFeatureAction(Request $request)
    {
        $validated = $request->validate([
            'user_view_id' => 'required|exists:user_view_permissions,id',
            'read'   => 'nullable|boolean',
            'write'  => 'nullable|boolean',
            'modify' => 'nullable|boolean',
            'delete' => 'nullable|boolean',
        ]);

        // If you want one record per user_view_id (update if exists)
        $permission = FeatureActionPermission::updateOrCreate(
            ['user_view_id' => $validated['user_view_id']],
            [
                'read'   => $validated['read'] ?? false,
                'write'  => $validated['write'] ?? false,
                'modify' => $validated['modify'] ?? false,
                'delete' => $validated['delete'] ?? false,
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Permission saved successfully',
            'data' => $permission
        ]);
    }



    public function showFeatureAction($user_view_id)
    {
        $permission = FeatureActionPermission::where('user_view_id', $user_view_id)->first();

        if (!$permission) {
            $permission = [
                'user_view_id' => $user_view_id,
                'read' => false,
                'write' => false,
                'modify' => false,
                'delete' => false,
            ];
        }

        return response()->json([
            'status' => true,
            'data' => $permission
        ]);
    }


    public function updateFeatureAction(Request $request, $user_view_id)
    {
        $validated = $request->validate([
            'read'   => 'nullable|boolean',
            'write'  => 'nullable|boolean',
            'modify' => 'nullable|boolean',
            'delete' => 'nullable|boolean',
        ]);

        $permission = FeatureActionPermission::updateOrCreate(
            ['user_view_id' => $user_view_id],
            [
                'read'   => $validated['read'] ?? false,
                'write'  => $validated['write'] ?? false,
                'modify' => $validated['modify'] ?? false,
                'delete' => $validated['delete'] ?? false,
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

}
