<?php

namespace App\Http\Controllers\GroupControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreGroupRequest;
use App\Http\Requests\Settings\UpdateGroupRequest;
use App\Models\Group;
use App\Models\GroupSupervisorMapping;
use App\Models\GroupTeamMapping;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Group::query()->latest();

        if ($this->usesNormalizedSchema()) {
            $query->with([
                'supervisorMappings.supervisor.role',
                'teamMappings.team',
            ]);

            if ($this->hasDeletedAtColumn()) {
                $query->whereNull('deleted_at');
            }
        }

        $groups = $query
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

        $group = DB::transaction(function () use ($validated): Group {
            if ($this->usesNormalizedSchema()) {
                $group = Group::create([
                    'group_name' => $validated['group_name'],
                    'status' => $validated['status'] ?? true,
                ]);

                $this->syncMappings($group, $validated['supervisor_id'] ?? [], $validated['team_id'] ?? []);

                return $group->load([
                    'supervisorMappings.supervisor.role',
                    'teamMappings.team',
                ]);
            }

            $group = Group::create([
                'name' => $validated['group_name'],
                'supervisor_id' => $validated['supervisor_id'] ?? [],
                'team_id' => $validated['team_id'] ?? [],
                'status' => $validated['status'] ?? true,
            ]);

            return $group->fresh();
        });

        return response()->json([
            'message' => 'Group created successfully.',
            'data' => $this->transformGroup($group),
        ], 201);
    }

    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        $validated = $request->validated();

        $group = DB::transaction(function () use ($group, $validated): Group {
            if ($this->usesNormalizedSchema()) {
                $group->update([
                    'group_name' => $validated['group_name'],
                    'status' => $validated['status'] ?? $group->status,
                ]);

                $this->syncMappings($group, $validated['supervisor_id'] ?? [], $validated['team_id'] ?? []);

                return $group->load([
                    'supervisorMappings.supervisor.role',
                    'teamMappings.team',
                ]);
            }

            $group->update([
                'name' => $validated['group_name'],
                'supervisor_id' => $validated['supervisor_id'] ?? [],
                'team_id' => $validated['team_id'] ?? [],
                'status' => $validated['status'] ?? $group->status,
            ]);

            return $group->fresh();
        });

        return response()->json([
            'message' => 'Group updated successfully.',
            'data' => $this->transformGroup($group),
        ]);
    }

    public function destroy(Group $group): JsonResponse
    {
        if ($this->usesNormalizedSchema()) {
            GroupSupervisorMapping::query()->where('group_id', $group->id)->delete();
            GroupTeamMapping::query()->where('group_id', $group->id)->delete();

            if ($this->hasDeletedAtColumn()) {
                $group->update(['deleted_at' => now()]);

                return response()->json([
                    'message' => 'Group deleted successfully.',
                ]);
            }
        }

        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully.',
        ]);
    }

    private function syncMappings(Group $group, array $supervisorIds, array $teamIds): void
    {
        GroupSupervisorMapping::query()->where('group_id', $group->id)->delete();
        GroupTeamMapping::query()->where('group_id', $group->id)->delete();

        foreach (array_values(array_unique($supervisorIds)) as $supervisorId) {
            GroupSupervisorMapping::create([
                'group_id' => $group->id,
                'supervisor_id' => $supervisorId,
            ]);
        }

        foreach (array_values(array_unique($teamIds)) as $teamId) {
            GroupTeamMapping::create([
                'group_id' => $group->id,
                'team_id' => $teamId,
            ]);
        }
    }

    private function transformGroup(Group $group): array
    {
        $groupName = $group->group_name ?? $group->name ?? '';
        $supervisorIds = $this->usesNormalizedSchema()
            ? $group->supervisorMappings->pluck('supervisor_id')->values()
            : $this->normalizeLegacyIds($group->supervisor_id ?? []);
        $teamIds = $this->usesNormalizedSchema()
            ? $group->teamMappings->pluck('team_id')->values()
            : $this->normalizeLegacyIds($group->team_id ?? []);

        return [
            'id' => $group->id,
            'group_name' => $groupName,
            'name' => $groupName,
            'status' => (bool) $group->status,
            'supervisor_id' => $supervisorIds,
            'team_id' => $teamIds,
            'supervisors' => $this->usesNormalizedSchema()
                ? $group->supervisorMappings
                    ->map(fn (GroupSupervisorMapping $mapping) => $this->transformSupervisorOption($mapping->supervisor))
                    ->filter()
                    ->values()
                : collect($supervisorIds)->map(fn ($id) => ['id' => $id, 'label' => (string) $id])->values(),
            'teams' => $this->usesNormalizedSchema()
                ? $group->teamMappings
                    ->map(fn (GroupTeamMapping $mapping) => $this->transformTeamOption($mapping->team))
                    ->filter()
                    ->values()
                : collect($teamIds)->map(fn ($id) => ['id' => $id, 'label' => (string) $id])->values(),
            'created_at' => $group->created_at,
            'updated_at' => $group->updated_at,
            'deleted_at' => $group->deleted_at ?? null,
        ];
    }

    private function transformSupervisorOption(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        $roleName = $user->role?->name;

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'user_name' => $user->user_name,
            'role_name' => $roleName,
            'label' => $roleName ? "{$user->full_name} ({$roleName})" : $user->full_name,
        ];
    }

    private function transformTeamOption(?Team $team): ?array
    {
        if (!$team) {
            return null;
        }

        return [
            'id' => $team->id,
            'team_name' => $team->team_name ?? $team->name ?? '',
            'label' => $team->team_name ?? $team->name ?? '',
        ];
    }

    private function normalizeLegacyIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }

    private function usesNormalizedSchema(): bool
    {
        return Schema::hasColumn('groups', 'group_name')
            && Schema::hasTable('group_supervisor_mappings')
            && Schema::hasTable('group_team_mappings');
    }

    private function hasDeletedAtColumn(): bool
    {
        return Schema::hasColumn('groups', 'deleted_at');
    }
}
