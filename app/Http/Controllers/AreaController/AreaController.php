<?php

namespace App\Http\Controllers\AreaController;

use App\Models\District;
use App\Models\Division;
use App\Models\Thana;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;

class AreaController extends Controller
{
    public function index(): JsonResponse
    {
        $divisions = Division::query()
            ->with([
                'districts.thanas',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Division $division) => $this->transformDivision($division))
            ->values();

        $districts = District::query()
            ->orderBy('name')
            ->get()
            ->map(fn (District $district) => $this->transformDistrict($district))
            ->values();

        $thanas = Thana::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Thana $thana) => $this->transformThana($thana))
            ->values();

        return response()->json([
            'message' => 'Area data fetched successfully.',
            'data' => [
                'divisions' => $divisions,
                'districts' => $districts,
                'thanas' => $thanas,
            ],
        ]);
    }

    public function districts(Division $division): JsonResponse
    {
        $districts = $division->districts()
            ->with('thanas')
            ->orderBy('name')
            ->get()
            ->map(fn (District $district) => $this->transformDistrict($district))
            ->values();

        return response()->json([
            'message' => 'Districts fetched successfully.',
            'data' => $districts,
        ]);
    }

    public function thanas(District $district): JsonResponse
    {
        $thanas = $district->thanas()
            ->orderBy('name')
            ->get()
            ->map(fn (Thana $thana) => $this->transformThana($thana))
            ->values();

        return response()->json([
            'message' => 'Thanas fetched successfully.',
            'data' => $thanas,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['division', 'district', 'thana'])],
            'name' => ['required', 'string', 'max:255'],
            'division_id' => ['required_if:type,district', 'nullable', 'integer', 'exists:divisions,id'],
            'district_id' => ['required_if:type,thana', 'nullable', 'integer', 'exists:districts,id'],
        ]);

        $model = match ($validated['type']) {
            'division' => Division::create([
                'name' => $validated['name'],
            ]),
            'district' => District::create([
                'division_id' => $validated['division_id'],
                'name' => $validated['name'],
            ]),
            'thana' => Thana::create([
                'district_id' => $validated['district_id'],
                'name' => $validated['name'],
            ]),
        };

        return response()->json([
            'message' => ucfirst($validated['type']).' created successfully.',
            'data' => $this->transformAreaModel($model),
        ], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'division_id' => [Rule::requiredIf($type === 'district'), 'nullable', 'integer', 'exists:divisions,id'],
            'district_id' => [Rule::requiredIf($type === 'thana'), 'nullable', 'integer', 'exists:districts,id'],
        ]);

        $model = $this->resolveAreaModel($type, $id);

        if ($model instanceof Division) {
            $model->update([
                'name' => $validated['name'],
            ]);
        } elseif ($model instanceof District) {
            $model->update([
                'division_id' => $validated['division_id'],
                'name' => $validated['name'],
            ]);
        } elseif ($model instanceof Thana) {
            $model->update([
                'district_id' => $validated['district_id'],
                'name' => $validated['name'],
            ]);
        }

        return response()->json([
            'message' => ucfirst($type).' updated successfully.',
            'data' => $this->transformAreaModel($model->fresh()),
        ]);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $model = $this->resolveAreaModel($type, $id);

        try {
            $model->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => ucfirst($type).' cannot be deleted because it is in use.',
            ], 422);
        }

        return response()->json([
            'message' => ucfirst($type).' deleted successfully.',
        ]);
    }

    private function resolveAreaModel(string $type, int $id): Model
    {
        return match ($type) {
            'division' => Division::query()->findOrFail($id),
            'district' => District::query()->findOrFail($id),
            'thana' => Thana::query()->findOrFail($id),
            default => abort(404, 'Area type not found.'),
        };
    }

    private function transformAreaModel(Model $model): array
    {
        return match (true) {
            $model instanceof Division => $this->transformDivision($model->loadMissing('districts.thanas')),
            $model instanceof District => $this->transformDistrict($model->loadMissing('thanas')),
            $model instanceof Thana => $this->transformThana($model),
            default => [],
        };
    }

    private function transformDivision(Division $division): array
    {
        return [
            'id' => $division->id,
            'name' => $division->name,
            'districts' => $division->districts
                ->map(fn (District $district) => $this->transformDistrict($district))
                ->values()
                ->all(),
            'created_at' => $division->created_at,
            'updated_at' => $division->updated_at,
        ];
    }

    private function transformDistrict(District $district): array
    {
        return [
            'id' => $district->id,
            'division_id' => $district->division_id,
            'name' => $district->name,
            'thanas' => $district->relationLoaded('thanas')
                ? $district->thanas->map(fn (Thana $thana) => $this->transformThana($thana))->values()->all()
                : [],
            'created_at' => $district->created_at,
            'updated_at' => $district->updated_at,
        ];
    }

    private function transformThana(Thana $thana): array
    {
        return [
            'id' => $thana->id,
            'district_id' => $thana->district_id,
            'name' => $thana->name,
            'created_at' => $thana->created_at,
            'updated_at' => $thana->updated_at,
        ];
    }
}
