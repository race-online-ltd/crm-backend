<?php

namespace App\Http\Controllers;

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
            ->pluck('feature_id'); // only feature IDs

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
            'feature_ids'   => 'required|array',
        ]);

        $role_id = $request->role_id;
        $navigation_id = $request->navigation_id;
        $feature_ids = $request->feature_ids;

        // ✅ Delete old permissions
        DB::table('user_view_permissions')
            ->where('role_id', $role_id)
            ->where('navigation_id', $navigation_id)
            ->delete();

        // ✅ Insert new ones
        $insertData = [];

        foreach ($feature_ids as $feature_id) {
            $insertData[] = [
                'role_id'       => $role_id,
                'navigation_id' => $navigation_id,
                'feature_id'    => $feature_id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        DB::table('user_view_permissions')->insert($insertData);

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully'
        ]);
    }


}
