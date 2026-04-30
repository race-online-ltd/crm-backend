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
use App\Models\UserBackofficeMapping;
use App\Models\UserChannelMapping;
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




    // public function storeUserMappings(Request $request)
    // {
    //     try {
    //         $validated = $request->validate([
    //             'userId' => 'nullable|integer|exists:users,id',
    //             'mapping' => 'required|array',
    //             'mapping.entityKamBindings' => 'required|array',
    //             'mapping.entityKamBindings.*.entityId' => 'nullable|integer|exists:business_entities,id',
    //             'mapping.entityKamBindings.*.kamIds' => 'nullable|array',
    //             'mapping.entityKamBindings.*.kamIds.*' => 'integer|exists:clients,id', // Change from users to clients
    //             'mapping.defaultEntityId' => 'nullable|integer|exists:business_entities,id',
    //             'mapping.defaultKamId' => 'nullable|integer|exists:clients,id', // Change from users to clients
    //             'mapping.teams' => 'nullable|array',
    //             'mapping.teams.selectAll' => 'boolean',
    //             'mapping.teams.ids' => 'nullable|array',
    //             'mapping.teams.ids.*' => 'integer|exists:teams,id',
    //             'mapping.teams.defaultId' => 'nullable|integer|exists:teams,id',
    //             'mapping.groups' => 'nullable|array',
    //             'mapping.groups.selectAll' => 'boolean',
    //             'mapping.groups.ids' => 'nullable|array',
    //             'mapping.groups.ids.*' => 'integer|exists:groups,id',
    //             'mapping.groups.defaultId' => 'nullable|integer|exists:groups,id',
    //             'mapping.divisions' => 'nullable|array',
    //             'mapping.divisions.selectAll' => 'boolean',
    //             'mapping.divisions.ids' => 'nullable|array',
    //             'mapping.divisions.ids.*' => 'integer|exists:divisions,id',
    //             'mapping.divisions.defaultId' => 'nullable|integer|exists:divisions,id',
    //             'mapping.backofficeIds' => 'nullable|array',
    //             'mapping.backofficeIds.*' => 'integer|exists:backoffice,id',

    //         ]);

    //         $userId = $validated['userId'];
    //         $mapping = $validated['mapping'];

    //         // If no user ID provided, try to get from authenticated user
    //         if (!$userId) {
    //             $userId = auth()->id();
    //         }

    //         if (!$userId) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'User ID is required'
    //             ], 400);
    //         }

    //         DB::beginTransaction();

    //         // 1. Store Business Entity - Client mappings
    //         $this->storeBusinessEntityMappings($userId, $mapping['entityKamBindings']);

    //         // 2. Store default mappings
    //         $this->storeDefaultMappings($userId, $mapping);

    //         // 3. Store team mappings
    //         if (isset($mapping['teams'])) {
    //             $this->storeTeamMappings($userId, $mapping['teams']);
    //         }

    //         // 4. Store group mappings
    //         if (isset($mapping['groups'])) {
    //             $this->storeGroupMappings($userId, $mapping['groups']);
    //         }

    //         // 5. Store division mappings
    //         if (isset($mapping['divisions'])) {
    //             $this->storeDivisionMappings($userId, $mapping['divisions']);
    //         }

    //         // 6. Store backoffice mappings (NEW)
    //         if (isset($mapping['backofficeIds']) && is_array($mapping['backofficeIds'])) {
    //             $this->storeBackofficeMappings($userId, $mapping['backofficeIds']);
    //         }


    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'User mappings saved successfully',
    //             'data' => [
    //                 'user_id' => $userId
    //             ]
    //         ], 200);

    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed',
    //             'errors' => $e->errors()
    //         ], 422);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Failed to store user mappings: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to save user mappings: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function storeUserMappings(Request $request)
    {
        try {
            $validated = $request->validate([
                'userId' => 'nullable|integer|exists:users,id',
                'mapping' => 'required|array',
                'mapping.entityKamBindings' => 'required|array',
                'mapping.entityKamBindings.*.entityId' => 'nullable|integer|exists:business_entities,id',
                'mapping.entityKamBindings.*.kamIds' => 'nullable|array',
                'mapping.entityKamBindings.*.kamIds.*' => 'integer|exists:clients,id',
                'mapping.defaultEntityId' => 'nullable|integer|exists:business_entities,id',
                'mapping.defaultKamId' => 'nullable|integer|exists:clients,id',
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
                'mapping.backoffices' => 'nullable|array',
                'mapping.backoffices.selectAll' => 'boolean',
                'mapping.backoffices.ids' => 'nullable|array',
                'mapping.backoffices.ids.*' => 'integer|exists:backoffice,id',
                'mapping.socials' => 'nullable|array',
                'mapping.socials.selectAll' => 'boolean',
                'mapping.socials.ids' => 'nullable|array',
                'mapping.socials.ids.*' => 'integer|exists:channels,id',
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

            // 2. Store default mappings (only for entity, kam, team, group, division)
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

            // 6. Store backoffice mappings (without default)
            if (isset($mapping['backoffices'])) {
                $this->storeBackofficeMappings($userId, $mapping['backoffices']);
            }

            // 7. Store social channel mappings (without default)
            if (isset($mapping['socials'])) {
                $this->storeSocialMappings($userId, $mapping['socials']);
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


    // private function storeBackofficeMappings($userId, $backofficeIds)
    // {
    //     // Delete existing mappings for this user
    //     UserBackofficeMapping::where('user_id', $userId)->delete();
 
    //     // Create new mappings for each backoffice
    //     if (!empty($backofficeIds)) {
    //         $mappings = array_map(function ($backofficeId) use ($userId) {
    //             return [
    //                 'user_id' => $userId,
    //                 'backoffice_id' => $backofficeId,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ];
    //         }, $backofficeIds);
 
    //         UserBackofficeMapping::insert($mappings);
    //     }
    // }


    // private function storeSocialMappings($userId, array $socials)
    // {
    //     // Delete existing mappings for this user
    //     UserChannelMapping::where('user_id', $userId)->delete();

    //     // If selectAll is true, get all channel IDs
    //     if (!empty($socials['selectAll'])) {
    //         $allChannels = \App\Models\Channel::pluck('id')->toArray();
    //         $channelIds = $allChannels;
    //     } else {
    //         $channelIds = $socials['ids'] ?? [];
    //     }

    //     // Create new mappings for each channel
    //     if (!empty($channelIds)) {
    //         $mappings = array_map(function ($channelId) use ($userId) {
    //             return [
    //                 'user_id' => $userId,
    //                 'channel_id' => $channelId,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ];
    //         }, $channelIds);

    //         UserChannelMapping::insert($mappings);
    //     }
    // }


    private function storeBackofficeMappings($userId, array $backoffices)
    {
        // Delete existing mappings for this user
        UserBackofficeMapping::where('user_id', $userId)->delete();

        // If selectAll is true, get all backoffice IDs
        if (!empty($backoffices['selectAll'])) {
            $allBackoffices = \App\Models\Backoffice::pluck('id')->toArray();
            $backofficeIds = $allBackoffices;
        } else {
            $backofficeIds = $backoffices['ids'] ?? [];
        }

        // Create new mappings for each backoffice
        if (!empty($backofficeIds)) {
            $mappings = array_map(function ($backofficeId) use ($userId) {
                return [
                    'user_id' => $userId,
                    'backoffice_id' => $backofficeId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $backofficeIds);

            UserBackofficeMapping::insert($mappings);
        }
    }

    private function storeSocialMappings($userId, array $socials)
    {
        // Delete existing mappings for this user
        UserChannelMapping::where('user_id', $userId)->delete();

        // If selectAll is true, get all channel IDs
        if (!empty($socials['selectAll'])) {
            $allChannels = \App\Models\Channel::pluck('id')->toArray();
            $channelIds = $allChannels;
        } else {
            $channelIds = $socials['ids'] ?? [];
        }

        // Create new mappings for each channel
        if (!empty($channelIds)) {
            $mappings = array_map(function ($channelId) use ($userId) {
                return [
                    'user_id' => $userId,
                    'channel_id' => $channelId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $channelIds);

            UserChannelMapping::insert($mappings);
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


    // public function getByBusinessEntity(Request $request)
    // {
    //     try {
    //         // Accept single or multiple IDs
    //         $businessEntityIds = $request->input('business_entity_id');

    //         // Convert to array if single value
    //         if (!is_array($businessEntityIds)) {
    //             $businessEntityIds = [$businessEntityIds];
    //         }

    //         $data = DB::table('backoffice as bo')
    //             ->select('bo.id', 'bo.backoffice_name')
    //             ->whereIn('bo.business_entity_id', $businessEntityIds)
    //             ->get();

    //         return response()->json([
    //             'status' => 'success',
    //             'data' => $data
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }





//     public function getByBusinessEntity(Request $request)
// {
//     try {
//         // Use $request->query() specifically for GET parameters
//         $businessEntityIds = $request->query('business_entity_id');

//         if (empty($businessEntityIds)) {
//             return response()->json(['status' => 'success', 'data' => []]);
//         }

//         // Ensure it's an array for whereIn
//         $idsArray = is_array($businessEntityIds) ? $businessEntityIds : explode(',', $businessEntityIds);

//         $data = DB::table('backoffice')
//             ->select('id', 'backoffice_name')
//             ->whereIn('business_entity_id', $idsArray)
//             ->get();

//         return response()->json([
//             'status' => 'success',
//             'data' => $data
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => 'error',
//             'message' => "Failed to get user mappings: " . $e->getMessage()
//         ], 500);
//     }
// }

}
