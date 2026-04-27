<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GetKamProductMappingRequest;
use App\Http\Requests\Settings\SaveKamProductMappingRequest;
use App\Models\BusinessEntity;
use App\Models\KamProductMapping;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class KamProductMappingController extends Controller
{
    public function index(): JsonResponse
    {
        $mappings = KamProductMapping::query()
            ->with([
                'user:id,full_name,user_name',
                'product:id,product_name,business_entity_id',
                'product.businessEntity:id,name',
            ])
            ->orderBy('user_id')
            ->orderBy('product_id')
            ->get()
            ->groupBy(fn (KamProductMapping $mapping) => $mapping->user_id . ':' . $mapping->product?->business_entity_id)
            ->map(fn ($group) => $this->transformMappingGroup($group))
            ->values();

        return response()->json([
            'message' => 'KAM product mappings fetched successfully.',
            'data' => $mappings,
        ]);
    }

    public function options(): JsonResponse
    {
        $users = User::query()
            ->where('status', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'user_name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->full_name ?: $user->user_name,
            ])
            ->values();

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
            'message' => 'KAM mapping options fetched successfully.',
            'data' => [
                'system_users' => $users,
                'business_entities' => $businessEntities,
            ],
        ]);
    }

    public function products(BusinessEntity $businessEntity): JsonResponse
    {
        $products = Product::query()
            ->where('business_entity_id', $businessEntity->id)
            ->orderBy('product_name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'label' => $product->product_name,
                'business_entity_id' => $product->business_entity_id,
            ])
            ->values();

        return response()->json([
            'message' => 'Products fetched successfully.',
            'data' => $products,
        ]);
    }

    public function show(GetKamProductMappingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $mappings = KamProductMapping::query()
            ->where('user_id', $validated['user_id'])
            ->whereHas('product', fn ($query) => $query->where('business_entity_id', $validated['business_entity_id']))
            ->with('product:id,product_name,business_entity_id')
            ->orderBy('id')
            ->get();

        return response()->json([
            'message' => 'KAM product mappings fetched successfully.',
            'data' => [
                'user_id' => $validated['user_id'],
                'business_entity_id' => $validated['business_entity_id'],
                'product_ids' => $mappings->pluck('product_id')->values(),
                'products' => $mappings
                    ->map(fn (KamProductMapping $mapping) => [
                        'id' => $mapping->product_id,
                        'label' => $mapping->product?->product_name,
                    ])
                    ->filter(fn (array $product) => !blank($product['label']))
                    ->values(),
            ],
        ]);
    }

    public function store(SaveKamProductMappingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validProductIds = Product::query()
            ->where('business_entity_id', $validated['business_entity_id'])
            ->whereIn('id', $validated['product_ids'])
            ->pluck('id')
            ->values();

        if ($validProductIds->count() !== count($validated['product_ids'])) {
            return response()->json([
                'message' => 'One or more selected products do not belong to the selected business entity.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $validProductIds): void {
            KamProductMapping::query()
                ->where('user_id', $validated['user_id'])
                ->whereHas('product', fn ($query) => $query->where('business_entity_id', $validated['business_entity_id']))
                ->delete();

            KamProductMapping::query()->insert(
                $validProductIds
                    ->map(fn (int $productId) => [
                        'user_id' => $validated['user_id'],
                        'product_id' => $productId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->all()
            );
        });

        $products = Product::query()
            ->whereIn('id', $validProductIds)
            ->orderBy('product_name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => $product->product_name,
            ])
            ->values();

        return response()->json([
            'message' => 'KAM product mappings saved successfully.',
            'data' => [
                'user_id' => $validated['user_id'],
                'business_entity_id' => $validated['business_entity_id'],
                'product_ids' => $validProductIds,
                'products' => $products,
            ],
        ]);
    }

    private function transformMappingGroup($group): array
    {
        $first = $group->first();
        $products = $group
            ->map(fn (KamProductMapping $mapping) => $mapping->product)
            ->filter()
            ->unique('id')
            ->values();

        return [
            'user_id' => $first?->user_id,
            'user_label' => $first?->user?->full_name ?: $first?->user?->user_name,
            'business_entity_id' => $first?->product?->businessEntity?->id,
            'business_entity_name' => $first?->product?->businessEntity?->name,
            'product_ids' => $products->pluck('id')->values(),
            'products' => $products->map(fn ($product) => [
                'id' => $product->id,
                'label' => $product->product_name,
            ])->values(),
        ];
    }
}
