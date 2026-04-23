<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GetApprovalPipelineStepsRequest;
use App\Http\Requests\Settings\SaveApprovalPipelineStepsRequest;
use App\Models\ApprovalPipelineStep;
use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ApprovalPipelineStepController extends Controller
{
    public function options(): JsonResponse
    {
        $businessEntities = BusinessEntity::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BusinessEntity $businessEntity) => [
                'id' => $businessEntity->id,
                'label' => $businessEntity->name,
            ])
            ->values();

        $systemUsers = User::query()
            ->with('role')
            ->where('status', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'user_name', 'role_id'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $this->formatUserLabel($user),
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
            ])
            ->values();

        return response()->json([
            'message' => 'Approval pipeline options fetched successfully.',
            'data' => [
                'business_entities' => $businessEntities,
                'system_users' => $systemUsers,
            ],
        ]);
    }

    public function show(GetApprovalPipelineStepsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $steps = ApprovalPipelineStep::query()
            ->with('user.role')
            ->where('business_entity_id', $validated['business_entity_id'])
            ->orderBy('level')
            ->orderBy('id')
            ->get()
            ->map(fn (ApprovalPipelineStep $step) => $this->transformStep($step))
            ->values();

        return response()->json([
            'message' => 'Approval pipeline steps fetched successfully.',
            'data' => [
                'business_entity_id' => $validated['business_entity_id'],
                'steps' => $steps,
            ],
        ]);
    }

    public function store(SaveApprovalPipelineStepsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $businessEntityId = $validated['business_entity_id'];
        $incomingSteps = collect($validated['steps'] ?? []);

        $this->ensureUniqueUserIds($incomingSteps);

        $existingSteps = ApprovalPipelineStep::query()
            ->where('business_entity_id', $businessEntityId)
            ->get()
            ->keyBy('id');

        $incomingIds = $incomingSteps
            ->pluck('id')
            ->filter()
            ->values();

        if ($incomingIds->isNotEmpty()) {
            $unknownIds = $incomingIds->diff($existingSteps->keys());

            if ($unknownIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'steps' => ['One or more approval steps do not belong to the selected business entity.'],
                ]);
            }
        }

        $savedSteps = DB::transaction(function () use ($businessEntityId, $incomingSteps, $existingSteps): Collection {
            $retainedIds = [];

            foreach ($incomingSteps->values() as $index => $stepPayload) {
                $attributes = [
                    'business_entity_id' => $businessEntityId,
                    'user_id' => $stepPayload['user_id'],
                    'level' => $index + 1,
                ];

                if (! empty($stepPayload['id'])) {
                    /** @var ApprovalPipelineStep $step */
                    $step = $existingSteps->get($stepPayload['id']);
                    $step->fill($attributes);
                    $step->save();
                } else {
                    $step = ApprovalPipelineStep::query()->create($attributes);
                }

                $retainedIds[] = $step->id;
            }

            $query = ApprovalPipelineStep::query()
                ->where('business_entity_id', $businessEntityId);

            if ($retainedIds !== []) {
                $query->whereNotIn('id', $retainedIds);
            }

            $query->delete();

            return ApprovalPipelineStep::query()
                ->with('user.role')
                ->where('business_entity_id', $businessEntityId)
                ->orderBy('level')
                ->orderBy('id')
                ->get();
        });

        return response()->json([
            'message' => 'Approval pipeline saved successfully.',
            'data' => [
                'business_entity_id' => $businessEntityId,
                'steps' => $savedSteps
                    ->map(fn (ApprovalPipelineStep $step) => $this->transformStep($step))
                    ->values(),
            ],
        ]);
    }

    private function ensureUniqueUserIds(Collection $steps): void
    {
        $userIds = $steps
            ->pluck('user_id')
            ->map(fn ($userId) => (int) $userId)
            ->filter();

        if ($userIds->count() !== $userIds->unique()->count()) {
            throw ValidationException::withMessages([
                'steps' => ['Each system user can only appear once within the selected business entity workflow.'],
            ]);
        }
    }

    private function transformStep(ApprovalPipelineStep $step): array
    {
        return [
            'id' => $step->id,
            'business_entity_id' => $step->business_entity_id,
            'user_id' => $step->user_id,
            'user_name' => $step->user?->full_name ?? $step->user?->user_name,
            'user_label' => $this->formatUserLabel($step->user),
            'level' => $step->level,
            'created_at' => $step->created_at,
            'updated_at' => $step->updated_at,
        ];
    }

    private function formatUserLabel(?User $user): string
    {
        if (! $user) {
            return '';
        }

        $base = $user->full_name ?: $user->user_name ?: "User {$user->id}";
        $roleName = $user->role?->name;

        return $roleName ? "{$base} ({$roleName})" : $base;
    }
}
