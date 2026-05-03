<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserMappingShowController extends Controller
{
    public function getUserNestedMapping()
{
    $userId = auth()->id();

    // default mapping
    $default = DB::table('user_default_mappings')
        ->where('user_id', $userId)
        ->first();

    // single query to fetch everything
    $rows = DB::table('business_entity_user_mappings as beum')
        ->join('business_entities as be', 'be.id', '=', 'beum.business_entity_id')
        ->join('users as u', 'u.id', '=', 'beum.kam_id')
        ->where('beum.user_id', $userId)
        ->select(
            'be.id as business_entity_id',
            'be.name as business_entity_name',
            'u.id as kam_id',
            'u.user_name as kam_name'
        )
        ->get();

    // transform (grouping)
    $grouped = $rows->groupBy('business_entity_id')->map(function ($items) {
        return [
            'id' => $items->first()->business_entity_id,
            'name' => $items->first()->business_entity_name,
            'kams' => $items->map(function ($item) {
                return [
                    'id' => $item->kam_id,
                    'name' => $item->kam_name
                ];
            })->unique('id')->values()
        ];
    })->values();

    return response()->json([
        'default' => $default,
        'business_entities' => $grouped
    ]);
}
}
