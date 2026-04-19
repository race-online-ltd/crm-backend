<?php

namespace App\Http\Controllers\MappingController;

use App\Http\Controllers\Controller;
use App\Models\BusinessEntityUserMapping;
use App\Models\UserDefaultMapping;
use App\Models\UserDivisionMapping;
use App\Models\UserGroupMapping;
use App\Models\UserTeamMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MappingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = Validator::make(
            $this->normalizePayload($request->all()),
            [
                'user_id' => ['required', 'integer', Rule::exists('users', 'id')],

                'business_entity_user_mappings' => ['sometimes', 'array'],
                'business_entity_user_mappings.*.business_entity_id' => [
                    'required',
                    'integer',
                    'distinct',
                    Rule::exists('business_entities', 'id'),
                ],
                'business_entity_user_mappings.*.kam_ids' => ['required', 'array', 'min:1'],
                'business_entity_user_mappings.*.kam_ids.*' => [
                    'required',
                    'integer',
                    'distinct',
                    Rule::exists('users', 'id'),
                ],

                'user_default_mapping.business_entity_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('business_entities', 'id'),
                ],
                'user_default_mapping.kam_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('users', 'id'),
                ],
                'user_default_mapping.team_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('teams', 'id'),
                ],
                'user_default_mapping.group_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('groups', 'id'),
                ],
                'user_default_mapping.division_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('divisions', 'id'),
                ],

                'team_ids' => ['sometimes', 'array'],
                'team_ids.*' => ['required', 'integer', 'distinct', Rule::exists('teams', 'id')],

                'group_ids' => ['sometimes', 'array'],
                'group_ids.*' => ['required', 'integer', 'distinct', Rule::exists('groups', 'id')],

                'division_ids' => ['sometimes', 'array'],
                'division_ids.*' => ['required', 'integer', 'distinct', Rule::exists('divisions', 'id')],
            ]
        )->validate();

        $userId = (int) $validated['user_id'];

        $entityMappings = collect($validated['business_entity_user_mappings'] ?? [])
            ->flatMap(function (array $binding) use ($userId) {
                return collect($binding['kam_ids'])->map(function (int $kamId) use ($binding, $userId) {
                    return [
                        'user_id' => $userId,
                        'business_entity_id' => (int) $binding['business_entity_id'],
                        'kam_id' => $kamId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                });
            })
            ->unique(fn (array $row) => $row['business_entity_id'].'::'.$row['kam_id'])
            ->values();

        $defaultMapping = $validated['user_default_mapping'] ?? [];
        $hasDefaultMapping = collect($defaultMapping)->contains(fn ($value) => $value !== null);

        $teamIds = collect($validated['team_ids'] ?? [])
            ->map(fn (int $teamId) => $teamId)
            ->unique()
            ->values();

        $groupIds = collect($validated['group_ids'] ?? [])
            ->map(fn (int $groupId) => $groupId)
            ->unique()
            ->values();

        $divisionIds = collect($validated['division_ids'] ?? [])
            ->map(fn (int $divisionId) => $divisionId)
            ->unique()
            ->values();

        DB::transaction(function () use (
            $userId,
            $entityMappings,
            $defaultMapping,
            $hasDefaultMapping,
            $teamIds,
            $groupIds,
            $divisionIds
        ): void {
            BusinessEntityUserMapping::query()->where('user_id', $userId)->delete();
            UserDefaultMapping::query()->where('user_id', $userId)->delete();
            UserTeamMapping::query()->where('user_id', $userId)->delete();
            UserGroupMapping::query()->where('user_id', $userId)->delete();
            UserDivisionMapping::query()->where('user_id', $userId)->delete();

            if ($entityMappings->isNotEmpty()) {
                BusinessEntityUserMapping::query()->insert($entityMappings->all());
            }

            if ($hasDefaultMapping) {
                UserDefaultMapping::query()->updateOrCreate(
                    ['user_id' => $userId],
                    [
                        'business_entity_id' => $defaultMapping['business_entity_id'] ?? null,
                        'kam_id' => $defaultMapping['kam_id'] ?? null,
                        'team_id' => $defaultMapping['team_id'] ?? null,
                        'group_id' => $defaultMapping['group_id'] ?? null,
                        'division_id' => $defaultMapping['division_id'] ?? null,
                    ]
                );
            }

            if ($teamIds->isNotEmpty()) {
                UserTeamMapping::query()->insert(
                    $teamIds->map(fn (int $teamId) => [
                        'user_id' => $userId,
                        'team_id' => $teamId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            }

            if ($groupIds->isNotEmpty()) {
                UserGroupMapping::query()->insert(
                    $groupIds->map(fn (int $groupId) => [
                        'user_id' => $userId,
                        'group_id' => $groupId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            }

            if ($divisionIds->isNotEmpty()) {
                UserDivisionMapping::query()->insert(
                    $divisionIds->map(fn (int $divisionId) => [
                        'user_id' => $userId,
                        'division_id' => $divisionId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            }
        });

        return response()->json([
            'message' => 'User mappings saved successfully.',
            'data' => [
                'user_id' => $userId,
                'business_entity_user_mappings_count' => $entityMappings->count(),
                'has_default_mapping' => $hasDefaultMapping,
                'team_mappings_count' => $teamIds->count(),
                'group_mappings_count' => $groupIds->count(),
                'division_mappings_count' => $divisionIds->count(),
            ],
        ]);
    }

    /**
     * Normalize the mixed frontend payload into a predictable shape for validation.
     */
    private function normalizePayload(array $input): array
    {
        $mapping = is_array(data_get($input, 'mapping')) ? data_get($input, 'mapping') : [];

        return [
            'user_id' => $this->normalizeNullableInt(
                data_get($input, 'user_id', data_get($input, 'userId'))
            ),
            'business_entity_user_mappings' => $this->normalizeEntityBindings(
                data_get($input, 'business_entity_user_mappings', data_get($mapping, 'entityKamBindings', []))
            ),
            'user_default_mapping' => [
                'business_entity_id' => $this->normalizeNullableInt(
                    data_get($input, 'user_default_mapping.business_entity_id', data_get($mapping, 'defaultEntityId'))
                ),
                'kam_id' => $this->normalizeNullableInt(
                    data_get($input, 'user_default_mapping.kam_id', data_get($mapping, 'defaultKamId'))
                ),
                'team_id' => $this->normalizeNullableInt(
                    data_get($input, 'user_default_mapping.team_id', data_get($mapping, 'teams.defaultId'))
                ),
                'group_id' => $this->normalizeNullableInt(
                    data_get($input, 'user_default_mapping.group_id', data_get($mapping, 'groups.defaultId'))
                ),
                'division_id' => $this->normalizeNullableInt(
                    data_get($input, 'user_default_mapping.division_id', data_get($mapping, 'divisions.defaultId'))
                ),
            ],
            'team_ids' => $this->normalizeIds(
                data_get($input, 'team_ids', data_get($mapping, 'teams.ids', []))
            ),
            'group_ids' => $this->normalizeIds(
                data_get($input, 'group_ids', data_get($mapping, 'groups.ids', []))
            ),
            'division_ids' => $this->normalizeIds(
                data_get($input, 'division_ids', data_get($mapping, 'divisions.ids', []))
            ),
        ];
    }

    /**
     * @return array<int, array{business_entity_id:int, kam_ids:array<int, int>}>
     */
    private function normalizeEntityBindings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $bindings = [];

        foreach ($value as $binding) {
            if (!is_array($binding)) {
                continue;
            }

            $businessEntityId = $this->normalizeNullableInt(
                $binding['business_entity_id'] ?? $binding['entityId'] ?? $binding['businessEntityId'] ?? null
            );

            $kamIds = $this->normalizeIds(
                $binding['kam_ids'] ?? $binding['kamIds'] ?? $binding['kam_id'] ?? $binding['kamId'] ?? []
            );

            if ($businessEntityId === null || $kamIds === []) {
                continue;
            }

            $bindings[] = [
                'business_entity_id' => $businessEntityId,
                'kam_ids' => $kamIds,
            ];
        }

        return $bindings;
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

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
