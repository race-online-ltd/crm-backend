<?php

namespace App\Http\Controllers;

use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\LeadAssign;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\LeadPipelineStage;
use App\Models\Backoffice;
use App\Models\Product;
use App\Models\Source;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class LeadController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $businessEntityId = $request->integer('business_entity_id');

        $businessEntities = BusinessEntity::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BusinessEntity $businessEntity) => [
                'id' => (string) $businessEntity->id,
                'label' => $businessEntity->name,
            ])
            ->values();

        $sources = Source::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Source $source) => [
                'id' => (string) $source->id,
                'label' => $source->name,
            ])
            ->values();

        $clients = Client::query()
            ->when($businessEntityId > 0, fn ($query) => $query->where('business_entity_id', $businessEntityId))
            ->orderBy('client_name')
            ->get([
                'id',
                'client_name',
                'contact_person',
                'contact_no',
                'email',
                'address',
                'lat',
                'long',
            ])
            ->map(fn (Client $client) => [
                'id' => (string) $client->id,
                'label' => $client->client_name,
                'client_name' => $client->client_name,
                'contact_person' => $client->contact_person,
                'contact_no' => $client->contact_no,
                'email' => $client->email,
                'address' => $client->address,
                'lat' => $client->lat,
                'long' => $client->long,
            ])
            ->values();

        $leadAssigns = LeadAssign::query()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (LeadAssign $leadAssign) => [
                'id' => (string) $leadAssign->id,
                'label' => $leadAssign->name,
            ])
            ->values();

        $kamUsers = User::query()
            ->join('kam_targets', 'kam_targets.kam_id', '=', 'users.id')
            ->when($businessEntityId > 0, fn ($query) => $query->where('kam_targets.business_entity_id', $businessEntityId))
            ->where('users.status', true)
            ->select('users.id', 'users.full_name', 'users.user_name')
            ->distinct()
            ->orderBy('users.full_name')
            ->orderBy('users.user_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => (string) $user->id,
                'label' => $user->full_name ?: $user->user_name,
            ])
            ->values();

        $backoffices = Backoffice::query()
            ->orderBy('backoffice_name')
            ->get(['id', 'backoffice_name'])
            ->map(fn (Backoffice $backoffice) => [
                'id' => (string) $backoffice->id,
                'label' => $backoffice->backoffice_name,
            ])
            ->values();

        $products = Product::query()
            ->when($businessEntityId > 0, fn ($query) => $query->where('business_entity_id', $businessEntityId))
            ->orderBy('product_name')
            ->get(['id', 'product_name', 'business_entity_id'])
            ->map(fn (Product $product) => [
                'id' => (string) $product->id,
                'label' => $product->product_name,
                'business_entity_id' => $product->business_entity_id,
            ])
            ->values();

        $stages = LeadPipelineStage::query()
            ->when($businessEntityId > 0, fn ($query) => $query->where('business_entity_id', $businessEntityId))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'business_entity_id', 'stage_name', 'color', 'sort_order'])
            ->map(fn (LeadPipelineStage $stage) => [
                'id' => (string) $stage->id,
                'label' => $stage->stage_name,
                'business_entity_id' => $stage->business_entity_id,
                'color' => $stage->color,
                'sort_order' => $stage->sort_order,
            ])
            ->values();

        return response()->json([
            'message' => 'Lead form options fetched successfully.',
            'data' => [
                'business_entities' => $businessEntities,
                'sources' => $sources,
                'clients' => $clients,
                'lead_assigns' => $leadAssigns,
                'kam_users' => $kamUsers,
                'backoffices' => $backoffices,
                'products' => $products,
                'stages' => $stages,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $leads = Lead::query()
            ->with([
                'businessEntity:id,name',
                'source:id,name',
                'leadAssign:id,name',
                'kam:id,full_name,user_name',
                'backoffice:id,backoffice_name',
                'client:id,client_name',
                'stage:id,stage_name,color',
                'products:id,product_name',
                'attachments:id,lead_id,file_name,file_path,mime_type,file_size',
                'creator:id,full_name,user_name',
                'updater:id,full_name,user_name',
            ])
            ->latest()
            ->get()
            ->map(fn (Lead $lead) => $this->transformLead($lead))
            ->values();

        return response()->json([
            'message' => 'Leads fetched successfully.',
            'data' => $leads,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$validated, $attachments] = $this->validateLeadRequest($request);

        $lead = DB::transaction(function () use ($validated, $attachments, $request): Lead {
            $lead = Lead::create($this->buildLeadAttributes($validated, $request));

            $lead->products()->sync($validated['product_ids']);
            $this->storeAttachments($lead, $attachments);

            return $lead->load($this->leadRelations());
        });

        return response()->json([
            'message' => 'Lead created successfully.',
            'data' => $this->transformLead($lead),
        ], 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->load($this->leadRelations());

        return response()->json([
            'message' => 'Lead fetched successfully.',
            'data' => $this->transformLead($lead),
        ]);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        [$validated, $attachments] = $this->validateLeadRequest($request, $lead->id);

        $lead = DB::transaction(function () use ($lead, $validated, $attachments, $request): Lead {
            $lead->update($this->buildLeadAttributes($validated, $request, true));

            $lead->products()->sync($validated['product_ids']);
            $this->storeAttachments($lead, $attachments, true);

            return $lead->load($this->leadRelations());
        });

        return response()->json([
            'message' => 'Lead updated successfully.',
            'data' => $this->transformLead($lead),
        ]);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return response()->json([
            'message' => 'Lead deleted successfully.',
        ]);
    }

    private function validateLeadRequest(Request $request, ?int $leadId = null): array
    {
        $payload = $this->normalizeLeadPayload($request);

        $validated = validator($payload, [
            'business_entity_id' => ['required', 'integer', Rule::exists('business_entities', 'id')],
            'source_id' => ['required', 'integer', Rule::exists('sources', 'id')],
            'source_info' => ['nullable', 'string'],
            'lead_assign_id' => ['required', 'integer', Rule::exists('lead_assign', 'id')],
            'kam_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'backoffice_id' => ['nullable', 'integer', Rule::exists('backoffice', 'id')],
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'lead_pipeline_stage_id' => [
                'required',
                'integer',
                Rule::exists('lead_pipeline_stages', 'id'),
            ],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('product', 'id')],
            'expected_revenue' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'],
            'attachments' => ['nullable', 'array'],
        ])->validate();

        $leadAssignName = LeadAssign::query()
            ->where('id', $validated['lead_assign_id'])
            ->value('name');

        $assignType = strtolower(trim((string) $leadAssignName));
        if ($assignType === 'kam') {
            if (empty($validated['kam_id'])) {
                abort(response()->json([
                    'message' => 'KAM user is required when lead_assign_id points to KAM.',
                ], 422));
            }
            $validated['backoffice_id'] = null;
        } elseif ($assignType === 'back office') {
            if (empty($validated['backoffice_id'])) {
                abort(response()->json([
                    'message' => 'Back office is required when lead_assign_id points to Back Office.',
                ], 422));
            }
            $validated['kam_id'] = null;
        }

        $stageBelongs = LeadPipelineStage::query()
            ->where('id', $validated['lead_pipeline_stage_id'])
            ->where('business_entity_id', $validated['business_entity_id'])
            ->exists();

        if (! $stageBelongs) {
            abort(response()->json([
                'message' => 'Selected stage does not belong to the selected business entity.',
            ], 422));
        }

        $productBelongs = Product::query()
            ->whereIn('id', $validated['product_ids'])
            ->where('business_entity_id', $validated['business_entity_id'])
            ->count();

        if ($productBelongs !== count($validated['product_ids'])) {
            abort(response()->json([
                'message' => 'One or more selected products do not belong to the selected business entity.',
            ], 422));
        }

        return [$validated, $this->extractUploadedFiles($request)];
    }

    private function buildLeadAttributes(array $validated, Request $request, bool $isUpdate = false): array
    {
        $attributes = [
            'business_entity_id' => $validated['business_entity_id'],
            'source_id' => $validated['source_id'],
            'client_id' => $validated['client_id'],
            'lead_pipeline_stage_id' => $validated['lead_pipeline_stage_id'],
            'expected_revenue' => $validated['expected_revenue'] ?? null,
            'deadline' => $validated['deadline'] ?? null,
            'updated_by' => $request->user()?->id,
        ];

        if (! $isUpdate) {
            $attributes['created_by'] = $request->user()?->id;
        }

        if (Schema::hasColumn('leads', 'source_info')) {
            $attributes['source_info'] = $validated['source_info'] ?? null;
        }

        if (Schema::hasColumn('leads', 'lead_assign_id')) {
            $attributes['lead_assign_id'] = $validated['lead_assign_id'] ?? null;
        }

        if (Schema::hasColumn('leads', 'kam_id')) {
            $attributes['kam_id'] = $validated['kam_id'] ?? null;
        }

        if (Schema::hasColumn('leads', 'backoffice_id')) {
            $attributes['backoffice_id'] = $validated['backoffice_id'] ?? null;
        }

        return $attributes;
    }

    private function normalizeLeadPayload(Request $request): array
    {
        $payload = $request->all();

        foreach (['product_ids', 'attachments'] as $key) {
            if (is_string($payload[$key] ?? null)) {
                $decoded = json_decode($payload[$key], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload[$key] = $decoded;
                }
            }
        }

        $assignType = strtolower(trim((string) (
            $payload['assigned_to_type']
            ?? $payload['assign_to_type']
            ?? ''
        )));
        $leadAssignId = $payload['lead_assign_id'] ?? null;

        if (! $leadAssignId && $assignType !== '') {
            $normalizedAssignName = $assignType === 'backoffice' || $assignType === 'back office'
                ? 'Back Office'
                : 'KAM';

            $leadAssignId = LeadAssign::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($normalizedAssignName)])
                ->value('id');
        }

        $kamId = $payload['kam_id']
            ?? $payload['assign_target_id']
            ?? $payload['assignTargetId']
            ?? $payload['assigned_to_user_id']
            ?? null;

        $backofficeId = $payload['backoffice_id']
            ?? $payload['backofficeId']
            ?? null;

        return [
            'business_entity_id' => (int) ($payload['business_entity_id'] ?? 0),
            'source_id' => (int) ($payload['source_id'] ?? 0),
            'source_info' => isset($payload['source_info']) && $payload['source_info'] !== ''
                ? trim((string) $payload['source_info'])
                : null,
            'lead_assign_id' => $leadAssignId ? (int) $leadAssignId : null,
            'kam_id' => $kamId !== null && $kamId !== ''
                ? (int) $kamId
                : null,
            'backoffice_id' => $backofficeId !== null && $backofficeId !== ''
                ? (int) $backofficeId
                : null,
            'client_id' => (int) ($payload['client_id'] ?? 0),
            'lead_pipeline_stage_id' => (int) ($payload['lead_pipeline_stage_id'] ?? 0),
            'product_ids' => array_values(array_filter(array_map('intval', (array) ($payload['product_ids'] ?? [])))),
            'expected_revenue' => isset($payload['expected_revenue']) && $payload['expected_revenue'] !== ''
                ? $payload['expected_revenue']
                : null,
            'deadline' => $payload['deadline'] ?? null,
            'attachments' => $payload['attachments'] ?? [],
        ];
    }

    private function extractUploadedFiles(Request $request): array
    {
        $files = $request->file('attachment');

        if (! $files) {
            return [];
        }

        return is_array($files) ? $files : [$files];
    }

    private function storeAttachments(Lead $lead, array $files, bool $replace = false): void
    {
        if ($replace && $files !== []) {
            $lead->attachments()->delete();
        }

        foreach ($files as $file) {
            $path = $file->store('lead-attachments', 'public');

            LeadAttachment::create([
                'lead_id' => $lead->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function leadRelations(): array
    {
        return [
            'businessEntity:id,name',
            'source:id,name',
            'leadAssign:id,name',
            'kam:id,full_name,user_name',
            'backoffice:id,backoffice_name',
            'client:id,client_name',
            'stage:id,stage_name,color',
            'products:id,product_name',
            'attachments:id,lead_id,file_name,file_path,mime_type,file_size',
            'creator:id,full_name,user_name',
            'updater:id,full_name,user_name',
        ];
    }

    private function transformLead(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'business_entity_id' => $lead->business_entity_id,
            'business_entity' => $lead->businessEntity?->name,
            'source_id' => $lead->source_id,
            'source' => $lead->source?->name,
            'source_info' => $lead->source_info,
            'lead_assign_id' => $lead->lead_assign_id,
            'lead_assign' => $lead->leadAssign?->name,
            'lead_assign_type' => $lead->leadAssign?->name,
            'kam_id' => $lead->kam_id,
            'kam' => $lead->kam?->full_name ?? $lead->kam?->user_name,
            'backoffice_id' => $lead->backoffice_id,
            'backoffice' => $lead->backoffice?->backoffice_name,
            'client_id' => $lead->client_id,
            'client' => $lead->client?->client_name,
            'lead_pipeline_stage_id' => $lead->lead_pipeline_stage_id,
            'stage' => $lead->stage?->stage_name,
            'expected_revenue' => $lead->expected_revenue,
            'deadline' => $lead->deadline,
            'product_ids' => $lead->products?->pluck('id')->values()->all() ?? [],
            'products' => $lead->products?->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => $product->product_name,
            ])->values()->all() ?? [],
            'attachment' => $lead->attachments?->map(fn (LeadAttachment $attachment) => [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'file_path' => $attachment->file_path,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
            ])->values()->all() ?? [],
            'created_by' => $lead->creator?->full_name ?? $lead->creator?->user_name,
            'updated_by' => $lead->updater?->full_name ?? $lead->updater?->user_name,
            'created_at' => $lead->created_at,
            'updated_at' => $lead->updated_at,
            'deleted_at' => $lead->deleted_at,
        ];
    }



// public function getLeadPipeline(Request $request): JsonResponse
// {
//     $perPage = (int) $request->get('per_page', 10);
//     $page = (int) $request->get('page', 1);

//     $authUser = $request->user();

//     if (!$authUser) {
//         return response()->json([
//             'message' => 'Unauthenticated'
//         ], 401);
//     }

//    $defaultData = DB::table('user_default_mappings as udm')
//         ->join('users as u', 'u.id', '=', 'udm.user_id')
//         ->join('business_entities as be', 'be.id', '=', 'udm.business_entity_id')
//         ->join('users as uk', 'uk.id', '=', 'udm.kam_id')
//         ->join('teams as t', 't.id', '=', 'udm.team_id')
//         ->join('groups as g', 'g.id', '=', 'udm.group_id')
//         ->join('divisions as d', 'd.id', '=', 'udm.division_id')
//         ->where('udm.user_id', $authUser->id)
//         ->where('u.status', 1)
//         ->where('t.status', 1)
//         ->where('g.status', 1)
//         ->select([
//             'udm.user_id',
//             'u.user_name',
//             'u.full_name',
//             'udm.business_entity_id',
//             'be.name as business_entity_name',
//             'udm.kam_id',
//             'uk.full_name as kam_name',
//             'udm.team_id',
//             't.name as team_name',
//             'udm.group_id',
//             'g.name as group_name',
//             'udm.division_id',
//             'd.name as division_name'
//         ])
//         ->orderBy('udm.user_id', 'desc')
//         ->first();

//         $businessEntityId = $defaultData->business_entity_id;
//         $kamId = $defaultData->kam_id;

//     // ✅ stages fetch
//     $stagePiplines = DB::table('lead_pipeline_stages')
//         ->whereIn('business_entity_id', [$businessEntityId])
//         ->where('is_active', 1)
//         ->orderBy('sort_order', 'asc')
//         ->get();

//     $leads = DB::select("SELECT
//                     l.id,
//                     l.business_entity_id,
//                     be.name AS business_entity_name,

//                     l.source_id,
//                     s.name AS source_name,
//                     l.source_info,

//                     l.lead_assign_id,
//                     la.name AS assign_type,

//                     l.kam_id,
//                     uk.full_name AS kam_name,

//                     l.backoffice_id,
//                     bak.backoffice_name,
//                     ub.full_name AS backoffice_user,

//                     l.client_id,
//                     c.client_name,

//                     l.lead_pipeline_stage_id,
//                     lps.stage_name,

//                     l.expected_revenue,
//                     l.deadline,

//                     uc.user_name AS created_by,
//                     uu.user_name AS updated_by,

//                     l.created_at,
//                     l.updated_at

//                 FROM leads l
//                 INNER JOIN business_entities be ON be.id = l.business_entity_id
//                 INNER JOIN sources s ON s.id = l.source_id
//                 INNER JOIN lead_assign la ON la.id = l.lead_assign_id
//                 INNER JOIN users uk ON uk.id = l.kam_id
//                 INNER JOIN backoffice bak ON bak.business_entity_id = l.business_entity_id
//                 LEFT JOIN backoffice_user_mapping bum ON bum.backoffice_id = l.backoffice_id
//                 LEFT JOIN users ub ON ub.id = bum.user_id
//                 INNER JOIN clients c ON c.id = l.client_id
//                 INNER JOIN lead_pipeline_stages lps ON lps.id = l.lead_pipeline_stage_id
//                 INNER JOIN users uc ON uc.id = l.created_by
//                 INNER JOIN users uu ON uu.id = l.updated_by

//                 WHERE l.business_entity_id = ?
//                 AND l.kam_id = ?
//                 ", [$businessEntityId, $kamId]);


//             $groupedLeads = collect($leads)->groupBy('lead_pipeline_stage_id');

//             $stageData = collect($stagePiplines)->map(function ($stage) use ($groupedLeads) {

//                 $leads = $groupedLeads[$stage->id] ?? collect([]);

//                 return [
//                     'stage_id' => $stage->id,
//                     'stage_name' => $stage->stage_name,
//                     'lead_count' => $leads->count(),
//                     'expected_revenue_sum' => $leads->sum(function ($lead) {
//                         return (float) $lead->expected_revenue;
//                     }),

//                     'leads' => $leads->values(), // reset index (optional but cleaner)
//                 ];
//             });
//             return response()->json([
//                 'message' => 'Lead pipeline stages fetched successfully.',
//                 'data' => [
//                     'default' => $defaultData,
//                     'stages' => $stageData
//                 ],
//             ]);

// }

public function getLeadPipeline(Request $request): JsonResponse
{
    $perPage = (int) $request->get('per_page', 2);
    $page = (int) $request->get('page', 1);

    $authUser = $request->user();

    if (!$authUser) {
        return response()->json([
            'message' => 'Unauthenticated'
        ], 401);
    }

    $defaultData = DB::table('user_default_mappings as udm')
        ->join('users as u', 'u.id', '=', 'udm.user_id')
        ->join('business_entities as be', 'be.id', '=', 'udm.business_entity_id')
        ->join('users as uk', 'uk.id', '=', 'udm.kam_id')
        ->join('teams as t', 't.id', '=', 'udm.team_id')
        ->join('groups as g', 'g.id', '=', 'udm.group_id')
        ->join('divisions as d', 'd.id', '=', 'udm.division_id')
        ->where('udm.user_id', $authUser->id)
        ->where('u.status', 1)
        ->where('t.status', 1)
        ->where('g.status', 1)
        ->select([
            'udm.user_id',
            'u.user_name',
            'u.full_name',
            'udm.business_entity_id',
            'be.name as business_entity_name',
            'udm.kam_id',
            'uk.full_name as kam_name',
            'udm.team_id',
            't.name as team_name',
            'udm.group_id',
            'g.name as group_name',
            'udm.division_id',
            'd.name as division_name'
        ])
        ->orderBy('udm.user_id', 'desc')
        ->first();

    $businessEntityId = $defaultData->business_entity_id;
    $kamId = $defaultData->kam_id;



    // current month range
    $startOfMonth = Carbon::now()->startOfMonth();
    $endOfMonth = Carbon::now()->endOfMonth();

    // get stage IDs for won & lost
    $wonStageIds = DB::table('lead_pipeline_stages')
        ->where('business_entity_id', $businessEntityId)
        ->where('stage_name', 'Won')
        ->pluck('id');

    $lostStageIds = DB::table('lead_pipeline_stages')
        ->where('business_entity_id', $businessEntityId)
        ->where('stage_name', 'Lost')
        ->pluck('id');

    // total leads (current month)
    $baseQuery = DB::table('leads')
        ->where('business_entity_id', $businessEntityId)
        ->where('kam_id', $kamId)
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

    // ✅ counts
    $wonCount = (clone $baseQuery)
        ->whereIn('lead_pipeline_stage_id', $wonStageIds)
        ->count();

    $lostCount = (clone $baseQuery)
        ->whereIn('lead_pipeline_stage_id', $lostStageIds)
        ->count();

    $activeCount = (clone $baseQuery)
        ->whereNotIn('lead_pipeline_stage_id', $wonStageIds->merge($lostStageIds))
        ->count();
        // ✅ forward count (current month)
    $forwardCount = DB::table('lead_assign_histories')
        ->where('business_entity_id', $businessEntityId)
        ->where('from_type', 1) // 1 = KAM
        ->where('from_id', $kamId)
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->count();

    // final summary
    $summary = [
        'won_lead_count' => $wonCount,
        'lost_lead_count' => $lostCount,
        'active_lead_count' => $activeCount,
        'forward_lead_count' => $forwardCount, // new metric
    ];

    // ✅ stages fetch (unchanged)
    $stagePiplines = DB::table('lead_pipeline_stages')
        ->whereIn('business_entity_id', [$businessEntityId])
        ->where('is_active', 1)
        ->orderBy('sort_order', 'asc')
        ->get();

    // ✅ leads fetch (unchanged)
    $leads = DB::select("SELECT
                    l.id,
                    l.business_entity_id,
                    be.name AS business_entity_name,

                    l.source_id,
                    s.name AS source_name,
                    l.source_info,

                    l.lead_assign_id,
                    la.name AS assign_type,

                    l.kam_id,
                    uk.full_name AS kam_name,

                    l.backoffice_id,
                    bak.backoffice_name,
                    ub.full_name AS backoffice_user,

                    l.client_id,
                    c.client_name,

                    l.lead_pipeline_stage_id,
                    lps.stage_name,

                    l.expected_revenue,
                    l.deadline,

                    uc.user_name AS created_by,
                    uu.user_name AS updated_by,

                    l.created_at,
                    l.updated_at

                FROM leads l
                INNER JOIN business_entities be ON be.id = l.business_entity_id
                INNER JOIN sources s ON s.id = l.source_id
                INNER JOIN lead_assign la ON la.id = l.lead_assign_id
                INNER JOIN users uk ON uk.id = l.kam_id
                INNER JOIN backoffice bak ON bak.business_entity_id = l.business_entity_id
                LEFT JOIN backoffice_user_mapping bum ON bum.backoffice_id = l.backoffice_id
                LEFT JOIN users ub ON ub.id = bum.user_id
                INNER JOIN clients c ON c.id = l.client_id
                INNER JOIN lead_pipeline_stages lps ON lps.id = l.lead_pipeline_stage_id
                INNER JOIN users uc ON uc.id = l.created_by
                INNER JOIN users uu ON uu.id = l.updated_by

                WHERE l.business_entity_id = ?
                AND l.kam_id = ?
                ", [$businessEntityId, $kamId]);

    // ✅ ADD: collect leads
    $leads = collect($leads);

    // ✅ ADD: get lead IDs
    $leadIds = $leads->pluck('id')->toArray();

    // ✅ ADD: products
    $products = DB::table('lead_products as lp')
        ->join('product as p', 'p.id', '=', 'lp.product_id')
        ->whereIn('lp.lead_id', $leadIds)
        ->select(
            'lp.lead_id',
            'p.id as product_id',
            'p.product_name'
        )
        ->get()
        ->groupBy('lead_id');

    // ✅ ADD: attachments
    $attachments = DB::table('lead_attachments')
        ->whereIn('lead_id', $leadIds)
        ->get()
        ->groupBy('lead_id');

    // ✅ ADD: attach to lead
    $leads = $leads->map(function ($lead) use ($products, $attachments) {

        $lead->products = $products[$lead->id] ?? [];
        $lead->attachments = $attachments[$lead->id] ?? [];

        return $lead;
    });

    // ✅ unchanged grouping
    $groupedLeads = $leads->groupBy('lead_pipeline_stage_id');

    // $stageData = collect($stagePiplines)->map(function ($stage) use ($groupedLeads) {

    //         $leads = $groupedLeads[$stage->id] ?? collect([]);

    //         // ✅ push into stage object
    //         $stage->lead_count = $leads->count();
    //         $stage->expected_revenue_sum = $leads->sum(function ($lead) {
    //             return (float) $lead->expected_revenue;
    //         });

    //         return [
    //             'stage' => $stage, // now includes count + sum inside
    //             'leads' => $leads->values(),
    //         ];
    //     });

    $stageData = collect($stagePiplines)->map(function ($stage) use ($groupedLeads, $perPage, $page) {

    $allLeads = collect($groupedLeads[$stage->id] ?? []);

    $total = $allLeads->count();

    // pagination logic
    $offset = ($page - 1) * $perPage;

    $paginatedLeads = $allLeads
        ->slice($offset, $perPage)
        ->values();

    // existing logic (unchanged)
    $stage->lead_count = $total;

    $stage->expected_revenue_sum = $allLeads->sum(function ($lead) {
        return (float) $lead->expected_revenue;
    });

    return [
        'stage' => $stage,

        // ✅ keep leads as array (as you want)
        'leads' => $paginatedLeads,

        // ✅ add pagination separately
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ],
    ];
});

    return response()->json([
        'message' => 'Lead pipeline stages fetched successfully.',
        'data' => [
            'summary' => $summary,
            'default' => $defaultData,
            'stages' => $stageData
        ],
    ]);
}

}
