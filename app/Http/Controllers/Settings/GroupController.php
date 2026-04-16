<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreGroupRequest;
use App\Http\Requests\Settings\UpdateGroupRequest;
use App\Models\Group;
use Illuminate\Http\JsonResponse;

class GroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = Group::query()
            ->latest()
            ->get()
            ->map(fn (Group $group) => $this->transformGroup($group))
            ->values();

        return response()->json([
            'message' => 'Groups fetched successfully.',
            'data' => $groups,
        ]);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $group = Group::create([
            'name' => $validated['name'],
            'supervisor_id' => $validated['supervisor_id'],
            'team_id' => $validated['team_id'],
            'status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'message' => 'Group created successfully.',
            'data' => $this->transformGroup($group),
        ], 201);
    }

    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        $validated = $request->validated();

        $group->update([
            'name' => $validated['name'],
            'supervisor_id' => $validated['supervisor_id'],
            'team_id' => $validated['team_id'],
            'status' => $validated['status'] ?? $group->status,
        ]);

        return response()->json([
            'message' => 'Group updated successfully.',
            'data' => $this->transformGroup($group->fresh()),
        ]);
    }

    public function destroy(Group $group): JsonResponse
    {
        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully.',
        ]);
    }

    private function transformGroup(Group $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'supervisor_id' => $group->supervisor_id ?? [],
            'team_id' => $group->team_id ?? [],
            'status' => (bool) $group->status,
            'created_at' => $group->created_at,
            'updated_at' => $group->updated_at,
        ];
    }
}
