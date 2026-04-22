<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GetLeadPipelineStagesRequest;
use App\Http\Requests\Settings\SaveLeadPipelineStagesRequest;
use App\Models\BusinessEntity;
use App\Models\LeadPipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadPipelineStageController extends Controller
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

        return response()->json([
            'message' => 'Lead pipeline options fetched successfully.',
            'data' => [
                'business_entities' => $businessEntities,
            ],
        ]);
    }

    public function show(GetLeadPipelineStagesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $stages = LeadPipelineStage::query()
            ->where('business_entity_id', $validated['business_entity_id'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LeadPipelineStage $stage) => $this->transformStage($stage))
            ->values();

        return response()->json([
            'message' => 'Lead pipeline stages fetched successfully.',
            'data' => [
                'business_entity_id' => $validated['business_entity_id'],
                'stages' => $stages,
            ],
        ]);
    }

    public function store(SaveLeadPipelineStagesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $businessEntityId = $validated['business_entity_id'];
        $incomingStages = collect($validated['stages'] ?? []);

        $this->ensureUniqueStageNames($incomingStages);

        $existingStages = LeadPipelineStage::query()
            ->where('business_entity_id', $businessEntityId)
            ->get()
            ->keyBy('id');

        $incomingIds = $incomingStages
            ->pluck('id')
            ->filter()
            ->values();

        if ($incomingIds->isNotEmpty()) {
            $unknownIds = $incomingIds->diff($existingStages->keys());

            if ($unknownIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'stages' => ['One or more stages do not belong to the selected business entity.'],
                ]);
            }
        }

        $savedStages = DB::transaction(function () use ($businessEntityId, $incomingStages, $existingStages): Collection {
            $retainedIds = [];

            foreach ($incomingStages->values() as $index => $stagePayload) {
                $attributes = [
                    'business_entity_id' => $businessEntityId,
                    'stage_name' => $stagePayload['stage_name'],
                    'color' => $stagePayload['color'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'deleted_at' => null,
                ];

                if (!empty($stagePayload['id'])) {
                    /** @var LeadPipelineStage $stage */
                    $stage = $existingStages->get($stagePayload['id']);
                    $stage->fill($attributes);
                    $stage->save();
                } else {
                    $stage = LeadPipelineStage::query()->create($attributes);
                }

                $retainedIds[] = $stage->id;
            }

            $query = LeadPipelineStage::query()
                ->where('business_entity_id', $businessEntityId);

            if ($retainedIds !== []) {
                $query->whereNotIn('id', $retainedIds);
            }

            $query->delete();

            return LeadPipelineStage::query()
                ->where('business_entity_id', $businessEntityId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });

        return response()->json([
            'message' => 'Lead pipeline stages saved successfully.',
            'data' => [
                'business_entity_id' => $businessEntityId,
                'stages' => $savedStages
                    ->map(fn (LeadPipelineStage $stage) => $this->transformStage($stage))
                    ->values(),
            ],
        ]);
    }

    private function ensureUniqueStageNames(Collection $stages): void
    {
        $normalizedNames = $stages
            ->pluck('stage_name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter();

        if ($normalizedNames->count() !== $normalizedNames->unique()->count()) {
            throw ValidationException::withMessages([
                'stages' => ['Stage names must be unique within the selected business entity.'],
            ]);
        }
    }

    private function transformStage(LeadPipelineStage $stage): array
    {
        return [
            'id' => $stage->id,
            'business_entity_id' => $stage->business_entity_id,
            'stage_name' => $stage->stage_name,
            'color' => $stage->color,
            'sort_order' => $stage->sort_order,
            'is_active' => (bool) $stage->is_active,
            'created_at' => $stage->created_at,
            'updated_at' => $stage->updated_at,
            'deleted_at' => $stage->deleted_at,
        ];
    }
}
