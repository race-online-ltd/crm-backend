<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveSystemAccountConnectionsRequest;
use App\Models\ExternalSystem;
use App\Models\InternalExternalUserMap;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SystemAccountConnectionController extends Controller
{
    public function externalSystemsIndex(): JsonResponse
    {
        $externalSystems = ExternalSystem::query()
            ->orderBy('external_system_name')
            ->get()
            ->map(fn (ExternalSystem $externalSystem) => [
                'id' => $externalSystem->id,
                'external_system_name' => $externalSystem->external_system_name,
                'external_system_api' => $externalSystem->external_system_api,
            ])
            ->values();

        return response()->json([
            'message' => 'External systems fetched successfully.',
            'data' => $externalSystems,
        ]);
    }

    public function externalSystemUsers(ExternalSystem $externalSystem): JsonResponse
    {
        if (blank($externalSystem->external_system_api)) {
            return response()->json([
                'message' => 'The selected external system does not have an API configured.',
            ], 422);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get($externalSystem->external_system_api)
                ->throw();
        } catch (RequestException $exception) {
            return response()->json([
                'message' => 'Unable to fetch users from the selected external system.',
                'details' => $exception->response?->json() ?? $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to fetch users from the selected external system.',
                'details' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'External system users fetched successfully.',
            'data' => $this->normalizeExternalUsers($response->json()),
        ]);
    }

    public function showUserConnections(User $systemUser): JsonResponse
    {
        $connections = InternalExternalUserMap::query()
            ->with('externalSystem')
            ->where('user_id', $systemUser->id)
            ->orderBy('id')
            ->get()
            ->map(fn (InternalExternalUserMap $mapping) => [
                'id' => $mapping->id,
                'external_system_id' => $mapping->external_system_id,
                'external_system_name' => $mapping->externalSystem?->external_system_name,
                'external_user_id' => $mapping->external_user_id,
            ])
            ->values();

        return response()->json([
            'message' => 'System account connections fetched successfully.',
            'data' => $connections,
        ]);
    }

    public function storeUserConnections(
        SaveSystemAccountConnectionsRequest $request,
        User $systemUser
    ): JsonResponse {
        $validated = $request->validated();
        $connections = collect($validated['connections'] ?? [])
            ->unique(fn (array $connection) => $connection['externalSystemId'].'::'.$connection['externalUserId'])
            ->values();

        DB::transaction(function () use ($systemUser, $connections): void {
            InternalExternalUserMap::query()
                ->where('user_id', $systemUser->id)
                ->delete();

            if ($connections->isEmpty()) {
                return;
            }

            InternalExternalUserMap::query()->insert(
                $connections
                    ->map(fn (array $connection) => [
                        'user_id' => $systemUser->id,
                        'external_system_id' => $connection['externalSystemId'],
                        'external_user_id' => $connection['externalUserId'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->all()
            );
        });

        return response()->json([
            'message' => 'System account connections saved successfully.',
        ]);
    }

    private function normalizeExternalUsers(mixed $payload): array
    {
        $records = $this->extractRecords($payload);

        return collect($records)
            ->map(function ($record) {
                if (is_scalar($record) || $record === null) {
                    $value = trim((string) $record);

                    return $value === ''
                        ? null
                        : ['id' => $value, 'label' => $value];
                }

                if (!is_array($record)) {
                    return null;
                }

                $id = $this->pickFirstScalar($record, [
                    'id',
                    'user_id',
                    'external_user_id',
                    'uuid',
                    'value',
                    'code',
                ]);

                $label = $this->pickFirstScalar($record, [
                    'label',
                    'name',
                    'full_name',
                    'fullName',
                    'username',
                    'user_name',
                    'email',
                    'title',
                ]);

                if ($id === null && $label === null) {
                    return null;
                }

                $normalizedId = trim((string) ($id ?? $label));
                $normalizedLabel = trim((string) ($label ?? $id));

                if ($normalizedId === '' || $normalizedLabel === '') {
                    return null;
                }

                return [
                    'id' => $normalizedId,
                    'label' => $normalizedLabel,
                ];
            })
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }

    private function extractRecords(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['data', 'results', 'users', 'items', 'records', 'value'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $records = $this->extractRecords($payload[$key]);

                if ($records !== []) {
                    return $records;
                }
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $records = $this->extractRecords($value);

                if ($records !== []) {
                    return $records;
                }
            }
        }

        return [];
    }

    private function pickFirstScalar(array $record, array $keys): string|int|float|bool|null
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $record)) {
                continue;
            }

            $value = $record[$key];

            if (is_scalar($value) && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
