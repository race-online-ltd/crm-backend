<?php

namespace App\Http\Controllers\TeamControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreTeamRequest;
use App\Http\Requests\Settings\UpdateTeamRequest;
use App\Models\Team;
use App\Models\TeamKamMapping;
use App\Models\TeamSupervisorMapping;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Team::query()->latest();

        if ($this->usesNormalizedSchema()) {
            $query->with([
                'supervisorMappings.supervisor.role',
                'kamMappings.kam.role',
            ]);
        }

        $teams = $query
            ->get()
            ->map(fn (Team $team) => $this->transformTeam($team))
            ->values();

        return response()->json([
            'message' => 'Teams fetched successfully.',
            'data' => $teams,
        ]);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $team = DB::transaction(function () use ($validated): Team {
            if ($this->usesNormalizedSchema()) {
                $team = Team::create([
                    'team_name' => $validated['team_name'],
                    'status' => $validated['status'] ?? true,
                ]);

                $this->syncMappings(
                    $team,
                    $validated['supervisor_id'] ?? [],
                    $validated['kam_id'] ?? [],
                );
            } else {
                $team = Team::create([
                    'name' => $validated['team_name'],
                    'supervisor_id' => $validated['supervisor_id'] ?? [],
                    'kam_id' => $validated['kam_id'] ?? [],
                    'status' => $validated['status'] ?? true,
                ]);
            }

            return $this->usesNormalizedSchema()
                ? $team->load([
                    'supervisorMappings.supervisor.role',
                    'kamMappings.kam.role',
                ])
                : $team;
        });

        return response()->json([
            'message' => 'Team created successfully.',
            'data' => $this->transformTeam($team),
        ], 201);
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $validated = $request->validated();

        $team = DB::transaction(function () use ($team, $validated): Team {
            if ($this->usesNormalizedSchema()) {
                $team->update([
                    'team_name' => $validated['team_name'],
                    'status' => $validated['status'] ?? $team->status,
                ]);

                $this->syncMappings(
                    $team,
                    $validated['supervisor_id'] ?? [],
                    $validated['kam_id'] ?? [],
                );

                return $team->load([
                    'supervisorMappings.supervisor.role',
                    'kamMappings.kam.role',
                ]);
            }

            $team->update([
                'name' => $validated['team_name'],
                'supervisor_id' => $validated['supervisor_id'] ?? [],
                'kam_id' => $validated['kam_id'] ?? [],
                'status' => $validated['status'] ?? $team->status,
            ]);

            return $team->fresh();
        });

        return response()->json([
            'message' => 'Team updated successfully.',
            'data' => $this->transformTeam($team),
        ]);
    }

    public function destroy(Team $team): JsonResponse
    {
        if ($this->usesNormalizedSchema()) {
            TeamSupervisorMapping::query()
                ->where('team_id', $team->id)
                ->delete();

            TeamKamMapping::query()
                ->where('team_id', $team->id)
                ->delete();
        }

        $team->delete();

        return response()->json([
            'message' => 'Team deleted successfully.',
        ]);
    }

    private function syncMappings(Team $team, array $supervisorIds, array $kamIds): void
    {
        TeamSupervisorMapping::query()
            ->where('team_id', $team->id)
            ->delete();

        TeamKamMapping::query()
            ->where('team_id', $team->id)
            ->delete();

        foreach (array_values(array_unique($supervisorIds)) as $supervisorId) {
            TeamSupervisorMapping::create([
                'team_id' => $team->id,
                'supervisor_id' => $supervisorId,
            ]);
        }

        foreach (array_values(array_unique($kamIds)) as $kamId) {
            TeamKamMapping::create([
                'team_id' => $team->id,
                'kam_id' => $kamId,
            ]);
        }
    }

    private function transformTeam(Team $team): array
    {
        $teamName = $team->team_name ?? $team->name ?? '';
        $supervisorIds = $this->usesNormalizedSchema()
            ? $team->supervisorMappings->pluck('supervisor_id')->values()
            : $this->normalizeLegacyIds($team->supervisor_id ?? []);
        $kamIds = $this->usesNormalizedSchema()
            ? $team->kamMappings->pluck('kam_id')->values()
            : $this->normalizeLegacyIds($team->kam_id ?? []);

        return [
            'id' => $team->id,
            'team_name' => $teamName,
            'name' => $teamName,
            'status' => (bool) $team->status,
            'supervisor_id' => $supervisorIds,
            'kam_id' => $kamIds,
            'supervisors' => $this->usesNormalizedSchema()
                ? $team->supervisorMappings
                    ->map(fn (TeamSupervisorMapping $mapping) => $this->transformUserOption($mapping->supervisor))
                    ->filter()
                    ->values()
                : collect($supervisorIds)->map(fn ($id) => ['id' => $id, 'label' => (string) $id])->values(),
            'kams' => $this->usesNormalizedSchema()
                ? $team->kamMappings
                    ->map(fn (TeamKamMapping $mapping) => $this->transformUserOption($mapping->kam))
                    ->filter()
                    ->values()
                : collect($kamIds)->map(fn ($id) => ['id' => $id, 'label' => (string) $id])->values(),
            'created_at' => $team->created_at,
            'updated_at' => $team->updated_at,
            'deleted_at' => $team->deleted_at ?? null,
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
        return Schema::hasColumn('teams', 'team_name')
            && Schema::hasTable('team_supervisor_mappings')
            && Schema::hasTable('team_kam_mappings');
    }

    private function transformUserOption(?User $user): ?array
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
}
