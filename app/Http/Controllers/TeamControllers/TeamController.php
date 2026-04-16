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
use Illuminate\Support\Collection;
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
        $supervisorIds = $this->normalizeIds($supervisorIds);
        $kamIds = $this->normalizeIds($kamIds);

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
        $supervisorIds = $this->resolveTeamSupervisorIds($team);
        $kamIds = $this->resolveTeamKamIds($team);
        $supervisors = $this->resolveSupervisorOptions($team, $supervisorIds);
        $kams = $this->resolveKamOptions($team, $kamIds);

        return [
            'id' => $team->id,
            'team_name' => $teamName,
            'name' => $teamName,
            'status' => (bool) $team->status,
            'supervisor_id' => $supervisorIds,
            'kam_id' => $kamIds,
            'supervisors' => $supervisors,
            'kams' => $kams,
            'supervisor_name' => $supervisors->pluck('label')->filter()->join(', '),
            'kam_name' => $kams->pluck('label')->filter()->join(', '),
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

        if (is_int($value)) {
            return [$value];
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_values(array_map('intval', $decoded));
            }

            if (is_numeric($value)) {
                return [(int) $value];
            }
        }

        return [];
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : [$value];

        return array_values(array_map(
            'intval',
            array_filter($items, static fn ($item) => $item !== null && $item !== '')
        ));
    }

    /**
     * @return array<int, int>
     */
    private function resolveTeamSupervisorIds(Team $team): array
    {
        $ids = collect();

        if ($this->usesNormalizedSchema()) {
            $ids = $ids->merge($team->supervisorMappings->pluck('supervisor_id')->all());
        }

        return $ids
            ->merge($this->normalizeLegacyIds($team->supervisor_id ?? []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function resolveTeamKamIds(Team $team): array
    {
        $ids = collect();

        if ($this->usesNormalizedSchema()) {
            $ids = $ids->merge($team->kamMappings->pluck('kam_id')->all());
        }

        return $ids
            ->merge($this->normalizeLegacyIds($team->kam_id ?? []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveSupervisorOptions(Team $team, array $supervisorIds): Collection
    {
        $options = collect();

        if ($this->usesNormalizedSchema()) {
            $options = $team->supervisorMappings
                ->map(fn (TeamSupervisorMapping $mapping) => $this->transformUserOption($mapping->supervisor))
                ->filter();
        }

        if (empty($supervisorIds)) {
            return $options->values();
        }

        $knownIds = $options->pluck('id')->all();
        $missingIds = array_values(array_diff($supervisorIds, $knownIds));

        if ($missingIds === []) {
            return $options->values();
        }

        $legacyUsers = User::query()
            ->with('role')
            ->whereIn('id', $missingIds)
            ->get()
            ->map(fn (User $user) => $this->transformUserOption($user))
            ->filter();

        return $options
            ->concat($legacyUsers)
            ->unique('id')
            ->values();
    }

    private function resolveKamOptions(Team $team, array $kamIds): Collection
    {
        $options = collect();

        if ($this->usesNormalizedSchema()) {
            $options = $team->kamMappings
                ->map(fn (TeamKamMapping $mapping) => $this->transformUserOption($mapping->kam))
                ->filter();
        }

        if (empty($kamIds)) {
            return $options->values();
        }

        $knownIds = $options->pluck('id')->all();
        $missingIds = array_values(array_diff($kamIds, $knownIds));

        if ($missingIds === []) {
            return $options->values();
        }

        $legacyUsers = User::query()
            ->with('role')
            ->whereIn('id', $missingIds)
            ->get()
            ->map(fn (User $user) => $this->transformUserOption($user))
            ->filter();

        return $options
            ->concat($legacyUsers)
            ->unique('id')
            ->values();
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
