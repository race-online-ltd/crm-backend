<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreBusinessEntityRequest;
use App\Http\Requests\Settings\UpdateBusinessEntityRequest;
use App\Models\BusinessEntity;
use Illuminate\Http\JsonResponse;

class BusinessEntityController extends Controller
{
    public function index(): JsonResponse
    {
        $businessEntities = BusinessEntity::query()
            ->orderBy('name')
            ->get()
            ->map(fn (BusinessEntity $businessEntity) => $this->transformBusinessEntity($businessEntity))
            ->values();

        return response()->json([
            'message' => 'Business entities fetched successfully.',
            'data' => $businessEntities,
        ]);
    }

    public function store(StoreBusinessEntityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $businessEntity = BusinessEntity::create([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'message' => 'Business entity created successfully.',
            'data' => $this->transformBusinessEntity($businessEntity),
        ], 201);
    }

    public function update(UpdateBusinessEntityRequest $request, BusinessEntity $businessEntity): JsonResponse
    {
        $validated = $request->validated();

        $businessEntity->update([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? $businessEntity->status,
        ]);

        return response()->json([
            'message' => 'Business entity updated successfully.',
            'data' => $this->transformBusinessEntity($businessEntity->fresh()),
        ]);
    }

    public function destroy(BusinessEntity $businessEntity): JsonResponse
    {
        $businessEntity->delete();

        return response()->json([
            'message' => 'Business entity deleted successfully.',
        ]);
    }

    private function transformBusinessEntity(BusinessEntity $businessEntity): array
    {
        return [
            'id' => $businessEntity->id,
            'name' => $businessEntity->name,
            'label' => $businessEntity->name,
            'status' => (bool) $businessEntity->status,
            'created_at' => $businessEntity->created_at,
            'updated_at' => $businessEntity->updated_at,
        ];
    }
}
