<?php

namespace App\Http\Controllers;

use App\Models\EntityColumnMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntityColumnMappingController extends Controller
{
    // ✅ Get all
    public function index()
    {
        return response()->json(EntityColumnMapping::latest()->get());
    }

    // ✅ Store
    public function store(Request $request)
    {
        $validated = $request->validate([
            'entity_id'   => 'required|integer',
            'page_id'     => 'nullable|integer',
            'table_id'    => 'nullable|integer',
            'column_id'   => 'nullable|integer',
        ]);

        $data = EntityColumnMapping::create($validated);

        return response()->json([
            'message' => 'Created successfully',
            'data' => $data
        ], 201);
    }

    // ✅ Show single
    public function show($id)
    {
        $data = EntityColumnMapping::findOrFail($id);
        return response()->json($data);
    }

    // ✅ Update
    public function update(Request $request, $id)
    {
        $data = EntityColumnMapping::findOrFail($id);

        $validated = $request->validate([
            'entity_id'   => 'sometimes|integer',
            'page_id'     => 'nullable|integer',
            'table_name'  => 'sometimes|string',
            'table_id'    => 'nullable|integer',
            'column_name' => 'sometimes|string',
            'column_id'   => 'nullable|integer',
        ]);

        $data->update($validated);

        return response()->json([
            'message' => 'Updated successfully',
            'data' => $data
        ]);
    }

    // ✅ Delete
    public function destroy($id)
    {
        $data = EntityColumnMapping::findOrFail($id);
        $data->delete();

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }


    public function getNavigationItems()
    {
        $data = DB::table('navigation_items')
            ->select('id', 'label')
            ->get();

        return response()->json($data);
    }


    public function getTableItems()
    {
        $data = DB::table('table_items')
            ->select('id', 'table_name as label')
            ->get();

        return response()->json($data);
    }


    public function getColumnItems()
    {
        $data = DB::table('column_items')
            ->select('id', 'column_name as label')
            ->get();

        return response()->json($data);
    }
}