<?php

namespace App\Http\Controllers\GroupControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreGroupRequest;
use App\Http\Requests\Settings\UpdateGroupRequest;
use App\Models\Group;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = Group::query()
            ->with([
                'supervisors.role',
                'teams',
            ])
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

        $group = DB::transaction(function () use ($validated): Group {
            $group = Group::create($this->groupPayload($validated));

            $this->syncAssignments(
                $group,
                $validated['supervisor_id'] ?? [],
                $validated['team_id'] ?? [],
            );

            return $group->load([
                'supervisors.role',
                'teams',
            ]);
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
            $group->update($this->groupPayload($validated, $group));

            $this->syncAssignments(
                $group,
                $validated['supervisor_id'] ?? [],
                $validated['team_id'] ?? [],
            );

            return $group->load([
                'supervisors.role',
                'teams',
            ]);
        });

        return response()->json([
            'message' => 'Group updated successfully.',
            'data' => $this->transformGroup($group),
        ]);
    }

    public function destroy(Group $group): JsonResponse
    {
        DB::transaction(function () use ($group): void {
            $group->supervisors()->detach();
            $group->teams()->detach();
            $group->delete();
        });

        return response()->json([
            'message' => 'Group deleted successfully.',
        ]);
    }

    /**
     * @param array<int, int> $supervisorIds
     * @param array<int, int> $teamIds
     */
    private function syncAssignments(Group $group, array $supervisorIds, array $teamIds): void
    {
        $group->supervisors()->sync($this->normalizeIds($supervisorIds));
        $group->teams()->sync($this->normalizeIds($teamIds));
    }

    private function transformGroup(Group $group): array
    {
        $supervisors = $group->supervisors
            ->map(fn (User $user) => $this->transformSupervisorOption($user))
            ->filter()
            ->values();

        $teams = $group->teams
            ->map(fn (Team $team) => $this->transformTeamOption($team))
            ->filter()
            ->values();

        return [
            'id' => $group->id,
            'group_name' => $this->resolveGroupName($group),
            'name' => $this->resolveGroupName($group),
            'status' => (bool) $group->status,
            'supervisor_id' => $supervisors->pluck('id')->values()->all(),
            'team_id' => $teams->pluck('id')->values()->all(),
            'supervisors' => $supervisors,
            'teams' => $teams,
            'supervisor_name' => $supervisors->pluck('label')->filter()->join(', '),
            'team_name' => $teams->pluck('label')->filter()->join(', '),
            'created_at' => $group->created_at,
            'updated_at' => $group->updated_at,
        ];
    }

    private function transformSupervisorOption(User $user): array
    {
        $roleName = $user->role?->name;

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'user_name' => $user->user_name,
            'role_name' => $roleName,
            'label' => $roleName ? "{$user->full_name} ({$roleName})" : $user->full_name,
        ];
    }

    private function transformTeamOption(Team $team): array
    {
        $teamName = $team->team_name ?? $team->name ?? '';

        return [
            'id' => $team->id,
            'team_name' => $teamName,
            'label' => $teamName,
        ];
    }

    /**
     * @param array<int, int> $values
     * @return array<int, int>
     */
    private function normalizeIds(array $values): array
    {
        return collect($values)
            ->filter(static fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array{group_name?: string, status?: bool} $validated
     * @return array<string, mixed>
     */
    private function groupPayload(array $validated, ?Group $group = null): array
    {
        $payload = [
            $this->groupNameColumn() => $validated['group_name'],
            'status' => $validated['status'] ?? ($group?->status ?? true),
        ];

        if (Schema::hasColumn('groups', 'name')) {
            $payload['name'] = $validated['group_name'];
        }

        return $payload;
    }

    private function groupNameColumn(): string
    {
        return Schema::hasColumn('groups', 'group_name') ? 'group_name' : 'name';
    }

    private function resolveGroupName(Group $group): string
    {
        $column = $this->groupNameColumn();

        return (string) ($group->{$column} ?? $group->name ?? $group->group_name ?? '');
    }
}
