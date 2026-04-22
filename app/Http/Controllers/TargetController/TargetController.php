<?php

namespace App\Http\Controllers\TargetController;

use App\Http\Controllers\Controller;
use App\Models\Backoffice;
use App\Models\BusinessEntity;
use App\Models\Group;
use App\Models\KamTarget;
use App\Models\Product;
use App\Models\Team;
use App\Models\UserDefaultMapping;
use App\Models\UserGroupMapping;
use App\Models\UserTeamMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TargetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KamTarget::query()
            ->with([
                'businessEntity:id,name',
                'kam:id,full_name,user_name',
                'product:id,product_name,business_entity_id',
                'creator:id,full_name,user_name',
            ])
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->whereHas('businessEntity', fn ($inner) => $inner->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('kam', fn ($inner) => $inner->where('full_name', 'like', "%{$search}%")->orWhere('user_name', 'like', "%{$search}%"))
                    ->orWhereHas('product', fn ($inner) => $inner->where('product_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('target_mode')) {
            $query->where('target_mode', $request->string('target_mode'));
        }

        if ($request->filled('team_id')) {
            $teamUserIds = $this->resolveUserIdsByTeam((int) $request->integer('team_id'));

            if (empty($teamUserIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('kam_id', $teamUserIds);
            }
        }

        if ($request->filled('group_id')) {
            $groupUserIds = $this->resolveUserIdsByGroup((int) $request->integer('group_id'));

            if (empty($groupUserIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('kam_id', $groupUserIds);
            }
        }

        $targets = $query->paginate((int) $request->integer('per_page', 15));

        return response()->json([
            'message' => 'Targets fetched successfully.',
            'data' => collect($targets->items())->map(fn (KamTarget $target) => $this->transformTarget($target))->values(),
            'meta' => [
                'current_page' => $targets->currentPage(),
                'last_page' => $targets->lastPage(),
                'per_page' => $targets->perPage(),
                'total' => $targets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_entity_id' => ['required', 'integer', Rule::exists('business_entities', 'id')],
            'kam_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'product_id' => ['required', 'integer', Rule::exists('product', 'id')],
            'target_mode' => ['required', Rule::in(['monthly', 'quarterly'])],
            'target_value' => ['required', 'integer'],
            'target_year' => ['required', 'integer', 'min:2000'],
            'revenue_target' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->validateBusinessEntityProductRelation(
            $validated['business_entity_id'],
            $validated['product_id']
        );

        $this->validateKamBelongsToBusinessEntity(
            $validated['business_entity_id'],
            $validated['kam_id']
        );

        $this->validateTargetValue($validated['target_mode'], $validated['target_value']);
        $this->ensureTargetDoesNotAlreadyExist(
            $validated['kam_id'],
            $validated['target_mode'],
            (int) $validated['target_year'],
            (int) $validated['target_value']
        );

        $target = DB::transaction(function () use ($validated, $request): KamTarget {
            return KamTarget::create([
                ...$validated,
                'created_by' => $request->user()?->id,
            ]);
        });

        return response()->json([
            'message' => 'Target created successfully.',
            'data' => $this->transformTarget($target->load([
                'businessEntity:id,name',
                'kam:id,full_name,user_name',
                'product:id,product_name,business_entity_id',
                'creator:id,full_name,user_name',
            ])),
        ], 201);
    }

    public function show(KamTarget $target): JsonResponse
    {
        $target->load([
            'businessEntity:id,name',
            'kam:id,full_name,user_name',
            'product:id,product_name,business_entity_id',
            'creator:id,full_name,user_name',
        ]);

        return response()->json([
            'message' => 'Target fetched successfully.',
            'data' => $this->transformTarget($target),
        ]);
    }

    public function update(Request $request, KamTarget $target): JsonResponse
    {
        $validated = $request->validate([
            'business_entity_id' => ['sometimes', 'integer', Rule::exists('business_entities', 'id')],
            'kam_id' => ['sometimes', 'integer', Rule::exists('users', 'id')],
            'product_id' => ['sometimes', 'integer', Rule::exists('product', 'id')],
            'target_mode' => ['sometimes', Rule::in(['monthly', 'quarterly'])],
            'target_value' => ['sometimes', 'integer'],
            'target_year' => ['sometimes', 'integer', 'min:2000'],
            'revenue_target' => ['sometimes', 'numeric', 'gt:0'],
        ]);

        $nextBusinessEntityId = $validated['business_entity_id'] ?? $target->business_entity_id;
        $nextKamId = $validated['kam_id'] ?? $target->kam_id;
        $nextProductId = $validated['product_id'] ?? $target->product_id;
        $nextTargetMode = $validated['target_mode'] ?? $target->target_mode;
        $nextTargetValue = $validated['target_value'] ?? $target->target_value;
        $nextTargetYear = $validated['target_year'] ?? $target->target_year;

        $this->validateBusinessEntityProductRelation($nextBusinessEntityId, $nextProductId);
        $this->validateKamBelongsToBusinessEntity($nextBusinessEntityId, $nextKamId);
        $this->validateTargetValue($nextTargetMode, $nextTargetValue);
        $this->ensureTargetDoesNotAlreadyExist(
            $nextKamId,
            $nextTargetMode,
            (int) $nextTargetYear,
            (int) $nextTargetValue,
            $target->id
        );

        $target->update([
            ...$validated,
            'created_by' => $target->created_by,
        ]);

        $target->load([
            'businessEntity:id,name',
            'kam:id,full_name,user_name',
            'product:id,product_name,business_entity_id',
            'creator:id,full_name,user_name',
        ]);

        return response()->json([
            'message' => 'Target updated successfully.',
            'data' => $this->transformTarget($target),
        ]);
    }

    public function destroy(KamTarget $target): JsonResponse
    {
        $target->delete();

        return response()->json([
            'message' => 'Target deleted successfully.',
        ]);
    }

    private function validateBusinessEntityProductRelation(int $businessEntityId, int $productId): void
    {
        $valid = Product::query()
            ->where('id', $productId)
            ->where('business_entity_id', $businessEntityId)
            ->exists();

        if (! $valid) {
            abort(response()->json([
                'message' => 'Selected product does not belong to the selected business entity.',
            ], 422));
        }
    }

    private function validateKamBelongsToBusinessEntity(int $businessEntityId, int $kamId): void
    {
        $valid = Backoffice::query()
            ->where('business_entity_id', $businessEntityId)
            ->whereHas('users', fn ($query) => $query->where('users.id', $kamId))
            ->exists();

        if (! $valid) {
            abort(response()->json([
                'message' => 'Selected KAM does not belong to the selected business entity.',
            ], 422));
        }
    }

    private function validateTargetValue(string $targetMode, int $targetValue): void
    {
        $isValid = $targetMode === 'monthly'
            ? $targetValue >= 1 && $targetValue <= 12
            : $targetValue >= 1 && $targetValue <= 4;

        if (! $isValid) {
            abort(response()->json([
                'message' => $targetMode === 'monthly'
                    ? 'Monthly target value must be between 1 and 12.'
                    : 'Quarterly target value must be between 1 and 4.',
            ], 422));
        }
    }

    private function ensureTargetDoesNotAlreadyExist(
        int $kamId,
        string $targetMode,
        int $targetYear,
        int $targetValue,
        ?int $ignoreTargetId = null
    ): void {
        $exists = KamTarget::query()
            ->where('kam_id', $kamId)
            ->where('target_mode', $targetMode)
            ->where('target_year', $targetYear)
            ->where('target_value', $targetValue)
            ->when($ignoreTargetId, fn ($query, int $targetId) => $query->where('id', '!=', $targetId))
            ->exists();

        if ($exists) {
            abort(response()->json([
                'message' => 'This KAM already has a target for the selected period.',
            ], 422));
        }
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserIdsByTeam(int $teamId): array
    {
        return collect()
            ->merge(
                UserTeamMapping::query()
                    ->where('team_id', $teamId)
                    ->pluck('user_id')
                    ->all()
            )
            ->merge(
                UserDefaultMapping::query()
                    ->where('team_id', $teamId)
                    ->pluck('user_id')
                    ->all()
            )
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserIdsByGroup(int $groupId): array
    {
        return collect()
            ->merge(
                UserGroupMapping::query()
                    ->where('group_id', $groupId)
                    ->pluck('user_id')
                    ->all()
            )
            ->merge(
                UserDefaultMapping::query()
                    ->where('group_id', $groupId)
                    ->pluck('user_id')
                    ->all()
            )
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserTeamIds(int $userId): array
    {
        return collect()
            ->merge(
                UserTeamMapping::query()
                    ->where('user_id', $userId)
                    ->pluck('team_id')
                    ->all()
            )
            ->merge(
                UserDefaultMapping::query()
                    ->where('user_id', $userId)
                    ->pluck('team_id')
                    ->all()
            )
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int,label:string}>
     */
    private function resolveUserTeams(int $userId)
    {
        $columns = ['id'];

        if (Schema::hasColumn('teams', 'team_name')) {
            $columns[] = 'team_name';
        }

        if (Schema::hasColumn('teams', 'name')) {
            $columns[] = 'name';
        }

        return Team::query()
            ->whereIn('id', $this->resolveUserTeamIds($userId))
            ->get($columns)
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'label' => $team->team_name ?: $team->name ?: "Team {$team->id}",
            ])
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserGroupIds(int $userId): array
    {
        return collect()
            ->merge(
                UserGroupMapping::query()
                    ->where('user_id', $userId)
                    ->pluck('group_id')
                    ->all()
            )
            ->merge(
                UserDefaultMapping::query()
                    ->where('user_id', $userId)
                    ->pluck('group_id')
                    ->all()
            )
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int,label:string}>
     */
    private function resolveUserGroups(int $userId)
    {
        $columns = ['id'];

        if (Schema::hasColumn('groups', 'group_name')) {
            $columns[] = 'group_name';
        }

        if (Schema::hasColumn('groups', 'name')) {
            $columns[] = 'name';
        }

        return Group::query()
            ->whereIn('id', $this->resolveUserGroupIds($userId))
            ->get($columns)
            ->map(fn (Group $group) => [
                'id' => $group->id,
                'label' => $group->group_name ?: $group->name ?: "Group {$group->id}",
            ])
            ->values();
    }

    private function transformTarget(KamTarget $target): array
    {
        return [
            'id' => $target->id,
            'business_entity_id' => $target->business_entity_id,
            'business_entity' => [
                'id' => $target->businessEntity?->id,
                'name' => $target->businessEntity?->name,
            ],
            'kam_id' => $target->kam_id,
            'kam' => [
                'id' => $target->kam?->id,
                'full_name' => $target->kam?->full_name,
                'user_name' => $target->kam?->user_name,
                'label' => $target->kam?->full_name ?: $target->kam?->user_name,
            ],
            'team_ids' => $this->resolveUserTeamIds($target->kam_id),
            'team_names' => $this->resolveUserTeams($target->kam_id)->pluck('label')->values()->all(),
            'group_ids' => $this->resolveUserGroupIds($target->kam_id),
            'group_names' => $this->resolveUserGroups($target->kam_id)->pluck('label')->values()->all(),
            'product_id' => $target->product_id,
            'product' => [
                'id' => $target->product?->id,
                'product_name' => $target->product?->product_name,
                'label' => $target->product?->product_name,
            ],
            'target_mode' => $target->target_mode,
            'target_value' => $target->target_value,
            'target_year' => $target->target_year,
            'revenue_target' => $target->revenue_target,
            'created_by' => $target->created_by,
            'creator' => [
                'id' => $target->creator?->id,
                'full_name' => $target->creator?->full_name,
                'user_name' => $target->creator?->user_name,
            ],
            'created_at' => $target->created_at,
            'updated_at' => $target->updated_at,
        ];
    }
}
