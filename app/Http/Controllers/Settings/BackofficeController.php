<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreBackofficeRequest;
use App\Http\Requests\Settings\UpdateBackofficeRequest;
use App\Models\Backoffice;
use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BackofficeController extends Controller
{
    public function index(): JsonResponse
    {
        $backoffices = Backoffice::query()
            ->with(['businessEntity:id,name', 'users:id,full_name,user_name'])
            ->latest()
            ->get()
            ->map(fn (Backoffice $backoffice) => $this->transformBackoffice($backoffice))
            ->values();

        return response()->json([
            'message' => 'Backoffice configurations fetched successfully.',
            'data' => $backoffices,
        ]);
    }

    public function store(StoreBackofficeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $backoffice = Backoffice::create([
            'backoffice_name' => $validated['backoffice_name'],
            'business_entity_id' => $validated['business_entity_id'],
        ]);

        $backoffice->users()->sync($validated['user_ids']);

        return response()->json([
            'message' => 'Backoffice configuration created successfully.',
            'data' => $this->transformBackoffice($backoffice->load(['businessEntity:id,name', 'users:id,full_name,user_name'])),
        ], 201);
    }

    public function update(UpdateBackofficeRequest $request, Backoffice $backoffice): JsonResponse
    {
        $validated = $request->validated();

        $backoffice->update([
            'backoffice_name' => $validated['backoffice_name'],
            'business_entity_id' => $validated['business_entity_id'],
        ]);

        $backoffice->users()->sync($validated['user_ids']);

        return response()->json([
            'message' => 'Backoffice configuration updated successfully.',
            'data' => $this->transformBackoffice($backoffice->load(['businessEntity:id,name', 'users:id,full_name,user_name'])),
        ]);
    }

    public function destroy(Backoffice $backoffice): JsonResponse
    {
        $backoffice->users()->detach();
        $backoffice->delete();

        return response()->json([
            'message' => 'Backoffice configuration deleted successfully.',
        ]);
    }

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

        $users = User::query()
            ->where('status', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'user_name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->full_name ?: $user->user_name,
            ])
            ->values();

        return response()->json([
            'message' => 'Backoffice form options fetched successfully.',
            'data' => [
                'business_entities' => $businessEntities,
                'system_users' => $users,
            ],
        ]);
    }

    private function transformBackoffice(Backoffice $backoffice): array
    {
        return [
            'id' => $backoffice->id,
            'backoffice_name' => $backoffice->backoffice_name,
            'business_entity_id' => $backoffice->business_entity_id,
            'business_entity_name' => $backoffice->businessEntity?->name,
            'system_users' => $backoffice->users
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'user_name' => $user->user_name,
                ])
                ->values(),
            'created_at' => $backoffice->created_at,
            'updated_at' => $backoffice->updated_at,
        ];
    }
}
