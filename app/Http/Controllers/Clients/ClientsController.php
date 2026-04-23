<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) request()->integer('per_page', 10), 100));
        $businessEntityId = $request->integer('business_entity_id');
        $clientName = trim((string) $request->input('client_name', ''));
        $divisionId = $request->integer('division_id');
        $contactPerson = trim((string) $request->input('contact_person', ''));
        $licence = trim((string) $request->input('licence', ''));
        $sortBy = (string) $request->input('sort_by', 'created_at');
        $sortDirection = strtolower((string) $request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $baseSortColumns = [
            'client_name' => 'clients.client_name',
            'origin' => 'clients.origin',
            'origin_id' => 'clients.origin_id',
            'contact_person' => 'clients.contact_person',
            'contact_no' => 'clients.contact_no',
            'email' => 'clients.email',
            'address' => 'clients.address',
            'licence' => 'clients.licence',
            'status' => 'clients.status',
            'created_at' => 'clients.created_at',
            'updated_at' => 'clients.updated_at',
        ];

        $query = Client::query()
            ->select('clients.*')
            ->with($this->clientRelations())
            ->when($businessEntityId > 0, function ($query) use ($businessEntityId): void {
                $query->where('business_entity_id', $businessEntityId);
            })
            ->when($clientName !== '', function ($query) use ($clientName): void {
                $query->where('client_name', 'like', '%'.$clientName.'%');
            })
            ->when($divisionId > 0, function ($query) use ($divisionId): void {
                $query->where('division_id', $divisionId);
            })
            ->when($contactPerson !== '', function ($query) use ($contactPerson): void {
                $query->where('contact_person', 'like', '%'.$contactPerson.'%');
            })
            ->when($licence !== '', function ($query) use ($licence): void {
                $query->where('licence', $licence);
            });

        if ($sortBy === 'business_entity_name') {
            $query
                ->leftJoin('business_entities', 'business_entities.id', '=', 'clients.business_entity_id')
                ->orderBy('business_entities.name', $sortDirection);
        } elseif ($sortBy === 'division_name') {
            $query
                ->leftJoin('divisions', 'divisions.id', '=', 'clients.division_id')
                ->orderBy('divisions.name', $sortDirection);
        } elseif ($sortBy === 'district_name') {
            $query
                ->leftJoin('districts', 'districts.id', '=', 'clients.district_id')
                ->orderBy('districts.name', $sortDirection);
        } elseif ($sortBy === 'thana_name') {
            $query
                ->leftJoin('thanas', 'thanas.id', '=', 'clients.thana_id')
                ->orderBy('thanas.name', $sortDirection);
        } else {
            $query->orderBy($baseSortColumns[$sortBy] ?? 'clients.created_at', $sortDirection);
        }

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Clients fetched successfully.',
            'data' => collect($paginator->items())
                ->map(fn (Client $client) => $this->transformClient($client))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateClient($request);

        $client = Client::create($validated);

        return response()->json([
            'message' => 'Client created successfully.',
            'data' => $this->transformClient($client->fresh()->load($this->clientRelations())),
        ], 201);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load($this->clientRelations());

        return response()->json([
            'message' => 'Client fetched successfully.',
            'data' => $this->transformClient($client),
        ]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $validated = $this->validateClient($request);

        $client->update($validated);

        return response()->json([
            'message' => 'Client updated successfully.',
            'data' => $this->transformClient($client->fresh()->load($this->clientRelations())),
        ]);
    }

    public function destroy(Client $client): JsonResponse
    {
        try {
            $client->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'Client could not be deleted.',
            ], 422);
        }

        return response()->json([
            'message' => 'Client deleted successfully.',
        ]);
    }

    /**
     * Keep eager-loaded relationships centralized so all CRUD responses stay consistent.
     *
     * @return array<int, string>
     */
    private function clientRelations(): array
    {
        return [
            'businessEntity:id,name',
            'division:id,name',
            'district:id,name',
            'thana:id,name',
        ];
    }

    private function validateClient(Request $request): array
    {
        return $request->validate([
            'business_entity_id' => ['required', 'integer', 'exists:business_entities,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'origin' => ['nullable', 'string', 'max:255'],
            'origin_id' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'long' => ['nullable', 'numeric', 'between:-180,180'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'thana_id' => ['required', 'integer', 'exists:thanas,id'],
            'licence' => ['required', Rule::in(['Active', 'Expire', 'Pending', 'None'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function transformClient(Client $client): array
    {
        return [
            'id' => $client->id,
            'business_entity_id' => $client->business_entity_id,
            'business_entity_name' => $client->businessEntity?->name,
            'client_name' => $client->client_name,
            'origin' => $client->origin,
            'origin_id' => $client->origin_id,
            'contact_person' => $client->contact_person,
            'contact_no' => $client->contact_no,
            'email' => $client->email,
            'address' => $client->address,
            'lat' => $client->lat,
            'long' => $client->long,
            'division_id' => $client->division_id,
            'district_id' => $client->district_id,
            'thana_id' => $client->thana_id,
            'division' => $client->division ? [
                'id' => $client->division->id,
                'name' => $client->division->name,
            ] : null,
            'district' => $client->district ? [
                'id' => $client->district->id,
                'name' => $client->district->name,
            ] : null,
            'thana' => $client->thana ? [
                'id' => $client->thana->id,
                'name' => $client->thana->name,
            ] : null,
            'licence' => $client->licence,
            'status' => $client->status,
            'deleted_at' => $client->deleted_at,
            'created_at' => $client->created_at,
            'updated_at' => $client->updated_at,
        ];
    }
}
