<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskReminderChannel;
use App\Models\TaskType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    private const CHECK_IN_RADIUS_METERS = 50;

    public function options(): JsonResponse
    {
        $leads = Lead::query()
            ->with(['client:id,client_name', 'businessEntity:id,name'])
            ->latest()
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => (string) $lead->id,
                'label' => trim(($lead->client?->client_name ?? 'Lead').' - '.($lead->businessEntity?->name ?? 'No Entity')),
                'client_id' => $lead->client_id,
                'lead_pipeline_stage_id' => $lead->lead_pipeline_stage_id,
            ])
            ->values();

        $clients = Client::query()
            ->orderBy('client_name')
            ->get(['id', 'client_name'])
            ->map(fn (Client $client) => [
                'id' => (string) $client->id,
                'label' => $client->client_name,
            ])
            ->values();

        $taskTypes = TaskType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TaskType $taskType) => [
                'id' => (string) $taskType->id,
                'label' => $taskType->name,
            ])
            ->values();

        $users = User::query()
            ->with('role:id,name')
            ->where('status', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'user_name', 'role_id'])
            ->map(fn (User $user) => [
                'id' => (string) $user->id,
                'label' => trim($user->full_name.' '.($user->role?->name ? "({$user->role?->name})" : '')),
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
                'role_name' => $user->role?->name,
            ])
            ->values();

        return response()->json([
            'message' => 'Task form options fetched successfully.',
            'data' => [
                'leads' => $leads,
                'clients' => $clients,
                'task_types' => $taskTypes,
                'users' => $users,
                'reminder_channels' => [
                    ['id' => 'google_calendar', 'label' => 'Google Calendar'],
                    ['id' => 'sms', 'label' => 'SMS'],
                ],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'task_type_id' => ['nullable', 'integer', Rule::exists('task_types', 'id')],
            'status' => ['nullable', Rule::in(['pending', 'overdue', 'completed', 'cancelled'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', Rule::in(['title', 'task_type', 'lead', 'scheduled_at', 'status'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->taskIndexQuery($validated);
        $paginator = $query->paginate((int) ($validated['per_page'] ?? 10))->withQueryString();

        return response()->json([
            'message' => 'Tasks fetched successfully.',
            'data' => collect($paginator->items())
                ->map(fn (Task $task) => $this->transformTask($task))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$validated, $files] = $this->validateTaskRequest($request);

        $task = DB::transaction(function () use ($validated, $files, $request): Task {
            $task = Task::create([
                'lead_id' => $validated['lead_id'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
                'created_by_user_id' => $request->user()?->id,
                'assignment_mode' => $validated['assignment_mode'] ?? 'self',
                'status' => 'pending',
                'task_type_id' => $validated['task_type_id'],
                'title' => $validated['title'],
                'details' => $validated['details'] ?? null,
                'scheduled_at' => $validated['scheduled_at'],
                'location_address' => $validated['location_address'] ?? null,
                'location_latitude' => $validated['location_latitude'] ?? null,
                'location_longitude' => $validated['location_longitude'] ?? null,
                'reminder_enabled' => (bool) $validated['reminder_enabled'],
                'reminder_offset_minutes' => $validated['reminder_offset_minutes'] ?? null,
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncReminderChannels($task, $validated['reminder_channels'] ?? []);
            $this->storeAttachments($task, $files);

            return $task->load($this->taskRelations());
        });

        return response()->json([
            'message' => 'Task created successfully.',
            'data' => $this->transformTask($task),
        ], 201);
    }

    public function show(Task $task): JsonResponse
    {
        $task->load($this->taskRelations());

        return response()->json([
            'message' => 'Task fetched successfully.',
            'data' => $this->transformTask($task),
        ]);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        [$validated, $files] = $this->validateTaskRequest($request, $task->id);

        $task = DB::transaction(function () use ($task, $validated, $files, $request): Task {
            $task->update([
                'lead_id' => $validated['lead_id'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
                'assignment_mode' => $validated['assignment_mode'] ?? 'self',
                'status' => $task->status,
                'task_type_id' => $validated['task_type_id'],
                'title' => $validated['title'],
                'details' => $validated['details'] ?? null,
                'scheduled_at' => $validated['scheduled_at'],
                'location_address' => $validated['location_address'] ?? null,
                'location_latitude' => $validated['location_latitude'] ?? null,
                'location_longitude' => $validated['location_longitude'] ?? null,
                'reminder_enabled' => (bool) $validated['reminder_enabled'],
                'reminder_offset_minutes' => $validated['reminder_offset_minutes'] ?? null,
                'updated_by' => $request->user()?->id,
            ]);

            $task->reminderChannels()->delete();
            $this->syncReminderChannels($task, $validated['reminder_channels'] ?? []);
            $this->storeAttachments($task, $files, true);

            return $task->load($this->taskRelations());
        });

        return response()->json([
            'message' => 'Task updated successfully.',
            'data' => $this->transformTask($task),
        ]);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully.',
        ]);
    }

    public function checkIn(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if (! $task->location_latitude || ! $task->location_longitude) {
            return response()->json([
                'message' => 'Task does not have a check-in location.',
            ], 422);
        }

        $distance = $this->calculateDistanceMeters(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $task->location_latitude,
            (float) $task->location_longitude,
        );

        if ($distance > self::CHECK_IN_RADIUS_METERS) {
            return response()->json([
                'message' => "You are too far from the task location to check in. You must be within ".self::CHECK_IN_RADIUS_METERS.' meters.',
                'data' => [
                    'distance_meters' => (int) round($distance),
                ],
            ], 422);
        }

        $task->update([
            'checked_in_at' => now(),
            'checked_in_latitude' => $validated['latitude'],
            'checked_in_longitude' => $validated['longitude'],
            'checked_in_distance_meters' => (int) round($distance),
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Task checked in successfully.',
            'data' => $this->transformTask($task->fresh()->load($this->taskRelations())),
        ]);
    }

    public function complete(Request $request, Task $task): JsonResponse
    {
        $validated = validator($this->normalizeCompletionPayload($request), [
            'completion_message' => ['required', 'string'],
        ])->validate();

        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_message' => $validated['completion_message'],
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Task completed successfully.',
            'data' => $this->transformTask($task->fresh()->load($this->taskRelations())),
        ]);
    }

    public function cancel(Request $request, Task $task): JsonResponse
    {
        $validated = validator($this->normalizeCancellationPayload($request), [
            'cancellation_reason' => ['required', 'string'],
        ])->validate();

        $task->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['cancellation_reason'],
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Task cancelled successfully.',
            'data' => $this->transformTask($task->fresh()->load($this->taskRelations())),
        ]);
    }

    private function validateTaskRequest(Request $request, ?int $taskId = null): array
    {
        $payload = $this->normalizeTaskPayload($request);

        $validated = validator($payload, [
            'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id')],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'assigned_to_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'assignment_mode' => ['nullable', Rule::in(['self', 'manual', 'delegated'])],
            'task_type_id' => ['required', 'integer', Rule::exists('task_types', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
            'location_address' => ['nullable', 'string'],
            'location_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'location_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'reminder_enabled' => ['sometimes', 'boolean'],
            'reminder_offset_minutes' => ['nullable', 'integer', 'min:1'],
            'reminder_channels' => ['nullable', 'array'],
            'reminder_channels.*' => ['required', 'string', Rule::in(['google_calendar', 'sms'])],
            'attachment' => ['nullable'],
        ])->validate();

        if (($validated['lead_id'] ?? null) && ($validated['client_id'] ?? null)) {
            throw ValidationException::withMessages([
                'lead_id' => 'A task can be linked to either a lead or a client, not both.',
            ]);
        }

        if (! ($validated['lead_id'] ?? null) && ! ($validated['client_id'] ?? null)) {
            throw ValidationException::withMessages([
                'lead_id' => 'Either a lead or a client must be selected.',
            ]);
        }

        if ((int) $validated['task_type_id'] === 1) {
            if (empty($validated['location_address']) || ! isset($validated['location_latitude']) || ! isset($validated['location_longitude'])) {
                throw ValidationException::withMessages([
                    'location_address' => 'Meeting location is required for physical meetings.',
                ]);
            }
        }

        return [$validated, $this->extractUploadedFiles($request)];
    }

    private function normalizeTaskPayload(Request $request): array
    {
        $payload = $request->all();

        foreach (['location', 'reminder', 'reminder_channels'] as $key) {
            if (is_string($payload[$key] ?? null)) {
                $decoded = json_decode($payload[$key], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload[$key] = $decoded;
                }
            }
        }

        $location = is_array($payload['location'] ?? null) ? $payload['location'] : [];
        $reminder = is_array($payload['reminder'] ?? null) ? $payload['reminder'] : [];

        return [
            'lead_id' => isset($payload['lead_id']) ? (int) $payload['lead_id'] : null,
            'client_id' => isset($payload['client_id']) ? (int) $payload['client_id'] : null,
            'assigned_to_user_id' => isset($payload['assigned_to_user_id']) ? (int) $payload['assigned_to_user_id'] : null,
            'assignment_mode' => $payload['assignment_mode'] ?? 'self',
            'task_type_id' => (int) ($payload['task_type_id'] ?? 0),
            'title' => trim((string) ($payload['title'] ?? '')),
            'details' => trim((string) ($payload['details'] ?? '')),
            'scheduled_at' => $payload['scheduled_at'] ?? null,
            'location_address' => $location['address'] ?? null,
            'location_latitude' => $location['latitude'] ?? null,
            'location_longitude' => $location['longitude'] ?? null,
            'status' => $payload['status'] ?? 'pending',
            'reminder_enabled' => filter_var($payload['reminder_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'reminder_offset_minutes' => isset($payload['reminder_offset_minutes']) && $payload['reminder_offset_minutes'] !== ''
                ? (int) $payload['reminder_offset_minutes']
                : null,
            'reminder_channels' => array_values(array_filter((array) ($payload['reminder_channels'] ?? ($reminder['channels'] ?? [])))),
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

    private function storeAttachments(Task $task, array $files, bool $replace = false): void
    {
        if ($replace && $files !== []) {
            $task->attachments()->delete();
        }

        foreach ($files as $file) {
            $path = $file->store('task-attachments', 'public');

            TaskAttachment::create([
                'task_id' => $task->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function syncReminderChannels(Task $task, array $channels): void
    {
        foreach (array_values(array_unique($channels)) as $channel) {
            TaskReminderChannel::create([
                'task_id' => $task->id,
                'channel' => $channel,
            ]);
        }
    }

    private function taskRelations(): array
    {
        return [
            'lead:id,client_id,business_entity_id',
            'lead.client:id,client_name',
            'lead.businessEntity:id,name',
            'client:id,client_name',
            'assignedToUser:id,full_name,user_name',
            'creator:id,full_name,user_name',
            'taskType:id,name',
            'reminderChannels:id,task_id,channel',
            'attachments:id,task_id,file_name,file_path,mime_type,file_size',
        ];
    }

    private function transformTask(Task $task): array
    {
        $status = $this->resolveStatus($task);

        return [
            'id' => $task->id,
            'lead_id' => $task->lead_id,
            'assocType' => $task->lead_id ? 'lead' : ($task->client_id ? 'client' : null),
            'lead' => $task->lead?->client?->client_name,
            'client_id' => $task->client_id,
            'client' => $task->client?->client_name,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_to_user_name' => $task->assignedToUser?->full_name ?? $task->assignedToUser?->user_name,
            'created_by_user_id' => $task->created_by_user_id,
            'created_by_user_name' => $task->creator?->full_name ?? $task->creator?->user_name,
            'assignment_mode' => $task->assignment_mode,
            'task_type_id' => $task->task_type_id,
            'taskType' => $this->normalizeTaskTypeKey($task->taskType?->name),
            'task_type_name' => $task->taskType?->name,
            'title' => $task->title,
            'details' => $task->details,
            'scheduledAt' => $task->scheduled_at,
            'location' => $task->location_address ? [
                'address' => $task->location_address,
                'latitude' => $task->location_latitude,
                'longitude' => $task->location_longitude,
            ] : null,
            'status' => $status,
            'reminderEnabled' => (bool) $task->reminder_enabled,
            'reminderOffsetMinutes' => $task->reminder_offset_minutes,
            'reminderChannels' => $task->reminderChannels?->pluck('channel')->values()->all() ?? [],
            'checked_in_at' => $task->checked_in_at,
            'checked_in_latitude' => $task->checked_in_latitude,
            'checked_in_longitude' => $task->checked_in_longitude,
            'checked_in_distance_meters' => $task->checked_in_distance_meters,
            'completed_at' => $task->completed_at,
            'completion_message' => $task->completion_message,
            'completionMessage' => $task->completion_message,
            'cancelled_at' => $task->cancelled_at,
            'cancellation_reason' => $task->cancellation_reason,
            'cancellationReason' => $task->cancellation_reason,
            'attachment' => $task->attachments?->map(fn (TaskAttachment $attachment) => [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'file_path' => $attachment->file_path,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
            ])->values()->all() ?? [],
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
            'deleted_at' => $task->deleted_at,
        ];
    }

    private function normalizeCompletionPayload(Request $request): array
    {
        return [
            'completion_message' => $request->input('completion_message', $request->input('completionMessage')),
        ];
    }

    private function normalizeCancellationPayload(Request $request): array
    {
        return [
            'cancellation_reason' => $request->input('cancellation_reason', $request->input('cancellationReason')),
        ];
    }

    private function resolveStatus(Task $task): string
    {
        if ($task->status === 'completed') {
            return 'completed';
        }

        if ($task->status === 'cancelled') {
            return 'cancelled';
        }

        if ($task->scheduled_at && $task->scheduled_at->isPast()) {
            return 'overdue';
        }

        return 'pending';
    }

    private function normalizeTaskTypeKey(?string $taskTypeName): string
    {
        $normalized = strtolower(trim((string) $taskTypeName));

        return match ($normalized) {
            'physical meeting' => 'physical_meeting',
            'virtual meeting' => 'virtual_meeting',
            'follow up' => 'follow_up',
            'call' => 'call',
            default => str_replace([' ', '-'], '_', $normalized),
        };
    }

    private function taskIndexQuery(array $filters)
    {
        $sortBy = $filters['sort_by'] ?? 'scheduled_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $query = Task::query()
            ->select('tasks.*')
            ->leftJoin('task_types as task_types_sort', 'task_types_sort.id', '=', 'tasks.task_type_id')
            ->leftJoin('leads as leads_sort', 'leads_sort.id', '=', 'tasks.lead_id')
            ->leftJoin('clients as lead_clients_sort', 'lead_clients_sort.id', '=', 'leads_sort.client_id')
            ->leftJoin('clients as task_clients_sort', 'task_clients_sort.id', '=', 'tasks.client_id')
            ->with($this->taskRelations());

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('tasks.title', 'like', "%{$search}%")
                    ->orWhere('tasks.details', 'like', "%{$search}%")
                    ->orWhere('task_types_sort.name', 'like', "%{$search}%")
                    ->orWhere('lead_clients_sort.client_name', 'like', "%{$search}%")
                    ->orWhere('task_clients_sort.client_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['task_type_id'])) {
            $query->where('tasks.task_type_id', (int) $filters['task_type_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('tasks.scheduled_at', '>=', Carbon::parse($filters['date_from'])->toDateString());
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('tasks.scheduled_at', '<=', Carbon::parse($filters['date_to'])->toDateString());
        }

        if (! empty($filters['status'])) {
            match ($filters['status']) {
                'completed' => $query->where('tasks.status', 'completed'),
                'cancelled' => $query->where('tasks.status', 'cancelled'),
                'overdue' => $query->where(function ($builder): void {
                    $builder->whereNull('tasks.status')
                        ->orWhereNotIn('tasks.status', ['completed', 'cancelled']);
                })->whereNotNull('tasks.scheduled_at')
                    ->where('tasks.scheduled_at', '<', now()),
                default => $query->where(function ($builder): void {
                    $builder->whereNull('tasks.status')
                        ->orWhereNotIn('tasks.status', ['completed', 'cancelled']);
                })->where(function ($builder): void {
                    $builder->whereNull('tasks.scheduled_at')
                        ->orWhere('tasks.scheduled_at', '>=', now());
                }),
            };
        }

        return $query->orderBy(match ($sortBy) {
            'title' => 'tasks.title',
            'task_type' => 'task_types_sort.name',
            'lead' => 'lead_clients_sort.client_name',
            'status' => DB::raw("CASE
                WHEN tasks.status = 'completed' THEN 3
                WHEN tasks.status = 'cancelled' THEN 4
                WHEN tasks.scheduled_at IS NOT NULL AND tasks.scheduled_at < NOW() THEN 2
                ELSE 1
            END"),
            default => 'tasks.scheduled_at',
        }, $sortOrder)
            ->orderBy('tasks.id', 'desc');
    }

    private function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }
}
