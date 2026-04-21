<?php

namespace App\Http\Controllers;

use App\Models\EntityColumnMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntityColumnMappingController extends Controller
{
    // ✅ Get all
    // public function index()
    // {
    //     return response()->json(EntityColumnMapping::latest()->get());
    // }

    public function index(Request $request)
    {
        $query = EntityColumnMapping::query();
 
        // Optional filters
        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }
        if ($request->has('page_id')) {
            $query->where('page_id', $request->page_id);
        }
        if ($request->has('table_id')) {
            $query->where('table_id', $request->table_id);
        }
 
        $mappings = $query->get();
 
        return response()->json([
            'message' => 'Mappings retrieved successfully',
            'data'    => $mappings
        ], 200);
    }

    // ✅ Store
    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'entity_id'   => 'required|integer',
    //         'page_id'     => 'nullable|integer',
    //         'table_id'    => 'nullable|integer',
    //         'column_id'   => 'nullable|integer',
    //     ]);

    //     $data = EntityColumnMapping::create($validated);

    //     return response()->json([
    //         'message' => 'Created successfully',
    //         'data' => $data
    //     ], 201);
    // }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'entity_id'  => 'required|integer|exists:business_entities,id',
            'page_id'    => 'required|integer|exists:navigation_items,id',
            'table_id'   => 'required|integer|exists:table_items,id',
            'column_id'  => 'required|integer|exists:column_items,id',
        ]);
 
        // Check if mapping already exists to prevent duplicates
        $existingMapping = EntityColumnMapping::where([
            'entity_id'  => $validated['entity_id'],
            'page_id'    => $validated['page_id'],
            'table_id'   => $validated['table_id'],
            'column_id'  => $validated['column_id'],
        ])->first();
 
        if ($existingMapping) {
            return response()->json([
                'message' => 'Mapping already exists',
                'data'    => $existingMapping
            ], 200);
        }
 
        $data = EntityColumnMapping::create($validated);
 
        return response()->json([
            'message' => 'Column mapping created successfully',
            'data'    => $data
        ], 201);
    }

    public function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'mappings'   => 'required|array',
            'mappings.*' => 'array',
            'mappings.*.entity_id'  => 'required|integer|exists:business_entities,id',
            'mappings.*.page_id'    => 'required|integer|exists:navigation_items,id',
            'mappings.*.table_id'   => 'required|integer|exists:table_items,id',
            'mappings.*.column_id'  => 'required|integer|exists:column_items,id',
        ]);
 
        $createdMappings = [];
        $skippedMappings = [];
 
        foreach ($validated['mappings'] as $mapping) {
            // Check if mapping already exists
            $existingMapping = EntityColumnMapping::where([
                'entity_id'  => $mapping['entity_id'],
                'page_id'    => $mapping['page_id'],
                'table_id'   => $mapping['table_id'],
                'column_id'  => $mapping['column_id'],
            ])->first();
 
            if ($existingMapping) {
                $skippedMappings[] = $mapping;
                continue;
            }
 
            $createdMapping = EntityColumnMapping::create($mapping);
            $createdMappings[] = $createdMapping;
        }
 
        return response()->json([
            'message'           => 'Bulk mappings processed',
            'created_count'     => count($createdMappings),
            'skipped_count'     => count($skippedMappings),
            'data'              => $createdMappings,
            'skipped'           => $skippedMappings
        ], 201);
    }


    public function destroy($id)
    {
        $mapping = EntityColumnMapping::findOrFail($id);
        $mapping->delete();
 
        return response()->json([
            'message' => 'Mapping deleted successfully'
        ], 200);
    }
 
    
    public function destroyByCriteria(Request $request)
    {
        $query = EntityColumnMapping::query();
 
        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }
        if ($request->has('page_id')) {
            $query->where('page_id', $request->page_id);
        }
        if ($request->has('table_id')) {
            $query->where('table_id', $request->table_id);
        }
 
        $deleted = $query->delete();
 
        return response()->json([
            'message' => "Deleted {$deleted} mappings",
            'deleted_count' => $deleted
        ], 200);
    }

    // ✅ Show single
    public function show($id)
    {
        $data = EntityColumnMapping::findOrFail($id);
        return response()->json($data);
    }

    // ✅ Update
    // public function update(Request $request, $id)
    // {
    //     $data = EntityColumnMapping::findOrFail($id);

    //     $validated = $request->validate([
    //         'entity_id'   => 'sometimes|integer',
    //         'page_id'     => 'nullable|integer',
    //         // 'table_name'  => 'sometimes|string',
    //         'table_id'    => 'nullable|integer',
    //         // 'column_name' => 'sometimes|string',
    //         'column_id'   => 'nullable|integer',
    //     ]);

    //     $data->update($validated);

    //     return response()->json([
    //         'message' => 'Updated successfully',
    //         'data' => $data
    //     ]);
    // }

    // public function update(Request $request, $id)
    // {
    //     $mapping = EntityColumnMapping::findOrFail($id);

    //     $validated = $request->validate([
    //         'entity_id' => 'required|integer|exists:business_entities,id',
    //         'page_id'   => 'required|integer|exists:page_navigation_items,id',
    //         'table_id'  => 'required|integer|exists:table_items,id',
    //         'column_id' => 'required|integer|exists:column_items,id',
    //     ]);

    //     $mapping->update($validated);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Mapping updated successfully',
    //         'data' => $mapping
    //     ]);
    // }


    public function update(Request $request)
{
    $validated = $request->validate([
        'page_id'   => 'required|integer|exists:navigation_items,id',
        'table_id'  => 'required|integer|exists:table_items,id',
        'mappings'  => 'required|array',
        'mappings.*.entity_id' => 'required|integer|exists:business_entities,id',
        'mappings.*.column_id' => 'required|integer|exists:column_items,id',
    ]);

    $page_id = $validated['page_id'];
    $table_id = $validated['table_id'];

    // ✅ Step 1: Delete old mappings
    EntityColumnMapping::where('page_id', $page_id)
        ->where('table_id', $table_id)
        ->delete();

    // ✅ Step 2: Insert new mappings
    $insertData = [];

    foreach ($validated['mappings'] as $item) {
        $insertData[] = [
            'entity_id'  => $item['entity_id'],
            'page_id'    => $page_id,
            'table_id'   => $table_id,
            'column_id'  => $item['column_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    EntityColumnMapping::insert($insertData);

    return response()->json([
        'success' => true,
        'message' => 'Mappings updated successfully'
    ]);
}

    // ✅ Delete
    // public function destroy($id)
    // {
    //     $data = EntityColumnMapping::findOrFail($id);
    //     $data->delete();

    //     return response()->json([
    //         'message' => 'Deleted successfully'
    //     ]);
    // }


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


    // public function getEntityWisetableColumnMappings($page_id, $table_id)
    // {
    //     $data = DB::table('entity_column_mappings as ecm')
    //         ->select('ecm.entity_id', 'ecm.page_id', 'ecm.table_id', 'ecm.column_id')
    //         ->where('ecm.page_id', $page_id)
    //         ->where('ecm.table_id', $table_id)
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $data
    //     ]);
    // }


    public function getEntityWisetableColumnMappings(Request $request)
    {
        $request->validate([
            'page_id'  => 'required|integer',
            'table_id' => 'required|integer',
        ]);

        $data = DB::table('entity_column_mappings as ecm')
            ->select('ecm.entity_id', 'ecm.page_id', 'ecm.table_id', 'ecm.column_id')
            ->where('ecm.page_id', $request->page_id)
            ->where('ecm.table_id', $request->table_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }



    // public function update(Request $request, $id)
    // {
    //     $validated = $request->validate([
    //         'entity_id'  => 'required|integer|exists:business_entities,id',
    //         'page_id'    => 'required|integer|exists:navigation_items,id',
    //         'table_id'   => 'required|integer|exists:table_items,id',
    //         'column_id'  => 'required|integer|exists:column_items,id',
    //     ]);

    //     $mapping = EntityColumnMapping::findOrFail($id);

    //     // Optional: prevent duplicate after update
    //     $exists = EntityColumnMapping::where([
    //         'entity_id'  => $validated['entity_id'],
    //         'page_id'    => $validated['page_id'],
    //         'table_id'   => $validated['table_id'],
    //         'column_id'  => $validated['column_id'],
    //     ])
    //     ->where('id', '!=', $id)
    //     ->exists();

    //     if ($exists) {
    //         return response()->json([
    //             'message' => 'Mapping already exists'
    //         ], 409);
    //     }

    //     $mapping->update($validated);

    //     return response()->json([
    //         'message' => 'Mapping updated successfully',
    //         'data'    => $mapping
    //     ]);
    // }
}