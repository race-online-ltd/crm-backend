<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Division;
use App\Http\Controllers\Controller;
use App\Models\BusinessEntityUserMapping;
use App\Models\UserDefaultMapping;
use App\Models\UserTeamMapping;
use App\Models\UserGroupMapping;
use App\Models\UserDivisionMapping;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class UserMappingController extends Controller
{
    public function getClientsByBusinessEntity(Request $request)
    {
        $request->validate([
            'business_entity_id' => 'required|integer|exists:clients,business_entity_id',
        ]);

        $clients = Client::select('id', 'business_entity_id', 'client_name')
            ->where('business_entity_id', $request->business_entity_id)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $clients
        ]);
    }



    public function getDivisions()
    {
        $divisions = Division::select('id', 'name')->get();

        return response()->json([
            'status' => true,
            'data' => $divisions
        ]);
    }




    public function storeUserMappings(Request $request)
    {
        try {
            $validated = $request->validate([
                'userId' => 'nullable|integer|exists:users,id',
                'mapping' => 'required|array',
                'mapping.entityKamBindings' => 'required|array',
                'mapping.entityKamBindings.*.entityId' => 'nullable|integer|exists:business_entities,id',
                'mapping.entityKamBindings.*.kamIds' => 'nullable|array',
                'mapping.entityKamBindings.*.kamIds.*' => 'integer|exists:clients,id', // Change from users to clients
                'mapping.defaultEntityId' => 'nullable|integer|exists:business_entities,id',
                'mapping.defaultKamId' => 'nullable|integer|exists:clients,id', // Change from users to clients
                'mapping.teams' => 'nullable|array',
                'mapping.teams.selectAll' => 'boolean',
                'mapping.teams.ids' => 'nullable|array',
                'mapping.teams.ids.*' => 'integer|exists:teams,id',
                'mapping.teams.defaultId' => 'nullable|integer|exists:teams,id',
                'mapping.groups' => 'nullable|array',
                'mapping.groups.selectAll' => 'boolean',
                'mapping.groups.ids' => 'nullable|array',
                'mapping.groups.ids.*' => 'integer|exists:groups,id',
                'mapping.groups.defaultId' => 'nullable|integer|exists:groups,id',
                'mapping.divisions' => 'nullable|array',
                'mapping.divisions.selectAll' => 'boolean',
                'mapping.divisions.ids' => 'nullable|array',
                'mapping.divisions.ids.*' => 'integer|exists:divisions,id',
                'mapping.divisions.defaultId' => 'nullable|integer|exists:divisions,id',
            ]);

            $userId = $validated['userId'];
            $mapping = $validated['mapping'];

            // If no user ID provided, try to get from authenticated user
            if (!$userId) {
                $userId = auth()->id();
            }

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
            }

            DB::beginTransaction();

            // 1. Store Business Entity - Client mappings
            $this->storeBusinessEntityMappings($userId, $mapping['entityKamBindings']);

            // 2. Store default mappings
            $this->storeDefaultMappings($userId, $mapping);

            // 3. Store team mappings
            if (isset($mapping['teams'])) {
                $this->storeTeamMappings($userId, $mapping['teams']);
            }

            // 4. Store group mappings
            if (isset($mapping['groups'])) {
                $this->storeGroupMappings($userId, $mapping['groups']);
            }

            // 5. Store division mappings
            if (isset($mapping['divisions'])) {
                $this->storeDivisionMappings($userId, $mapping['divisions']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User mappings saved successfully',
                'data' => [
                    'user_id' => $userId
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to store user mappings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save user mappings: ' . $e->getMessage()
            ], 500);
        }
    }

   
    private function storeBusinessEntityMappings($userId, array $entityKamBindings)
    {
        // Delete existing mappings for this user
        BusinessEntityUserMapping::where('user_id', $userId)->delete();

        // Create new mappings
        foreach ($entityKamBindings as $binding) {
            if (empty($binding['entityId']) || empty($binding['kamIds'])) {
                continue;
            }

            foreach ($binding['kamIds'] as $kamId) {
                BusinessEntityUserMapping::create([
                    'user_id' => $userId,
                    'business_entity_id' => $binding['entityId'],
                    'kam_id' => $kamId, // This will now store client IDs
                ]);
            }
        }
    }

    
    private function storeDefaultMappings($userId, array $mapping)
    {
        // Delete existing default mapping for this user
        UserDefaultMapping::where('user_id', $userId)->delete();

        // Create new default mapping
        UserDefaultMapping::create([
            'user_id' => $userId,
            'business_entity_id' => $mapping['defaultEntityId'] ?? null,
            'kam_id' => $mapping['defaultKamId'] ?? null, // This will now store client ID
            'team_id' => $mapping['teams']['defaultId'] ?? null,
            'group_id' => $mapping['groups']['defaultId'] ?? null,
            'division_id' => $mapping['divisions']['defaultId'] ?? null,
        ]);
    }

    
    private function storeTeamMappings($userId, array $teams)
    {
        // Delete existing team mappings for this user
        UserTeamMapping::where('user_id', $userId)->delete();

        // If selectAll is true, get all team IDs
        if (!empty($teams['selectAll'])) {
            $allTeams = \App\Models\Team::pluck('id')->toArray();
            $teamIds = $allTeams;
        } else {
            $teamIds = $teams['ids'] ?? [];
        }

        // Create new team mappings
        foreach ($teamIds as $teamId) {
            UserTeamMapping::create([
                'user_id' => $userId,
                'team_id' => $teamId,
            ]);
        }
    }

    
    private function storeGroupMappings($userId, array $groups)
    {
        // Delete existing group mappings for this user
        UserGroupMapping::where('user_id', $userId)->delete();

        // If selectAll is true, get all group IDs
        if (!empty($groups['selectAll'])) {
            $allGroups = \App\Models\Group::pluck('id')->toArray();
            $groupIds = $allGroups;
        } else {
            $groupIds = $groups['ids'] ?? [];
        }

        // Create new group mappings
        foreach ($groupIds as $groupId) {
            UserGroupMapping::create([
                'user_id' => $userId,
                'group_id' => $groupId,
            ]);
        }
    }

    
    private function storeDivisionMappings($userId, array $divisions)
    {
        // Delete existing division mappings for this user
        UserDivisionMapping::where('user_id', $userId)->delete();

        // If selectAll is true, get all division IDs
        if (!empty($divisions['selectAll'])) {
            $allDivisions = \App\Models\Division::pluck('id')->toArray();
            $divisionIds = $allDivisions;
        } else {
            $divisionIds = $divisions['ids'] ?? [];
        }

        // Create new division mappings
        foreach ($divisionIds as $divisionId) {
            UserDivisionMapping::create([
                'user_id' => $userId,
                'division_id' => $divisionId,
            ]);
        }
    }

    
    public function getUserMappings($userId)
    {
        try {
            $user = User::findOrFail($userId);

            $mappings = [
                'entityKamBindings' => $this->getBusinessEntityMappings($userId),
                'defaultEntityId' => null,
                'defaultKamId' => null,
                'teams' => [
                    'selectAll' => false,
                    'ids' => $this->getTeamMappings($userId),
                    'defaultId' => null,
                ],
                'groups' => [
                    'selectAll' => false,
                    'ids' => $this->getGroupMappings($userId),
                    'defaultId' => null,
                ],
                'divisions' => [
                    'selectAll' => false,
                    'ids' => $this->getDivisionMappings($userId),
                    'defaultId' => null,
                ],
            ];

            // Get default mappings
            $defaultMapping = UserDefaultMapping::where('user_id', $userId)->first();
            if ($defaultMapping) {
                $mappings['defaultEntityId'] = $defaultMapping->business_entity_id;
                $mappings['defaultKamId'] = $defaultMapping->kam_id;
                $mappings['teams']['defaultId'] = $defaultMapping->team_id;
                $mappings['groups']['defaultId'] = $defaultMapping->group_id;
                $mappings['divisions']['defaultId'] = $defaultMapping->division_id;
            }

            return response()->json([
                'success' => true,
                'data' => $mappings
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get user mappings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user mappings'
            ], 500);
        }
    }

   
    private function getBusinessEntityMappings($userId)
    {
        $mappings = BusinessEntityUserMapping::where('user_id', $userId)
            ->with(['businessEntity', 'kam'])
            ->get();

        $grouped = [];
        foreach ($mappings as $mapping) {
            $entityId = $mapping->business_entity_id;
            if (!isset($grouped[$entityId])) {
                $grouped[$entityId] = [
                    'entityId' => $entityId,
                    'kamIds' => []
                ];
            }
            $grouped[$entityId]['kamIds'][] = $mapping->kam_id;
        }

        return array_values($grouped);
    }

   
    private function getTeamMappings($userId)
    {
        return UserTeamMapping::where('user_id', $userId)
            ->pluck('team_id')
            ->toArray();
    }

    
    private function getGroupMappings($userId)
    {
        return UserGroupMapping::where('user_id', $userId)
            ->pluck('group_id')
            ->toArray();
    }

   
    private function getDivisionMappings($userId)
    {
        return UserDivisionMapping::where('user_id', $userId)
            ->pluck('division_id')
            ->toArray();
    }

}
