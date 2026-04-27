<?php

namespace App\Http\Controllers;

use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskNote;
use App\Models\TaskNoteAttachment;
use App\Models\TaskReminderChannel;
use App\Models\TaskType;
use App\Models\Team;
use App\Models\UserDefaultMapping;
use App\Models\UserGroupMapping;
use App\Models\UserTeamMapping;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function calendarFilters(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);

        if ($userId <= 0) {
            return response()->json([
                'message' => 'Task calendar filters fetched successfully.',
                'data' => [
                    'business_entities' => [],
                    'teams' => [],
                    'groups' => [],
                    'kams' => [],
                ],
            ]);
        }

        $visibleBusinessEntityIds = $this->taskCalendarBusinessEntityIds($userId);
        $visibleKamIds = $this->taskCalendarKamIds($userId);
        $teamIds = $this->resolveUserTeamIds($userId);
        $groupIds = $this->resolveUserGroupIds($userId);

        $businessEntities = BusinessEntity::query()
            ->when($visibleBusinessEntityIds !== [], fn ($query) => $query->whereIn('id', $visibleBusinessEntityIds))
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BusinessEntity $businessEntity) => [
                'id' => (string) $businessEntity->id,
                'label' => $businessEntity->name,
            ])
            ->values();

        $teams = Team::query()
            ->when($teamIds !== [], fn ($query) => $query->whereIn('id', $teamIds))
            ->where('status', true)
            ->orderByRaw('COALESCE(team_name, name)')
            ->get(['id', 'team_name', 'name'])
            ->map(fn (Team $team) => [
                'id' => (string) $team->id,
                'label' => (string) ($team->team_name ?: $team->name ?: "Team {$team->id}"),
            ])
            ->values();

        $groups = Group::query()
            ->when($groupIds !== [], fn ($query) => $query->whereIn('id', $groupIds))
            ->where('status', true)
            ->orderByRaw('COALESCE(group_name, name)')
            ->get(['id', 'group_name', 'name'])
            ->map(fn (Group $group) => [
                'id' => (string) $group->id,
                'label' => (string) ($group->group_name ?: $group->name ?: "Group {$group->id}"),
            ])
            ->values();

        $kams = User::query()
            ->whereIn('id', $visibleKamIds)
            ->where('status', true)
            ->orderBy('full_name')
            ->orderBy('user_name')
            ->get(['id', 'full_name', 'user_name'])
            ->map(fn (User $user) => [
                'id' => (string) $user->id,
                'label' => $user->full_name ?: $user->user_name,
            ])
            ->values();

        return response()->json([
            'message' => 'Task calendar filters fetched successfully.',
            'data' => [
                'business_entities' => $businessEntities,
                'teams' => $teams,
                'groups' => $groups,
                'kams' => $kams,
            ],
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'business_entity_id' => ['nullable', 'integer', Rule::exists('business_entities', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')],
            'kam_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $tasks = $this->taskCalendarQuery($validated, $request->user())
            ->get()
            ->map(fn (Task $task) => $this->transformTask($task))
            ->values();

        return response()->json([
            'message' => 'Task calendar fetched successfully.',
            'data' => $tasks,
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
                'reminder_at' => $validated['reminder_at'] ?? null,
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
                'reminder_at' => $validated['reminder_at'] ?? null,
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

    public function storeNote(Request $request, Task $task): JsonResponse
    {
        $validated = validator($this->normalizeNotePayload($request), [
            'content' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ])->validate();

        $note = DB::transaction(function () use ($task, $validated, $request): TaskNote {
            $note = TaskNote::create([
                'task_id' => $task->id,
                'created_by_user_id' => $request->user()?->id,
                'content' => $validated['content'],
            ]);

            $this->storeNoteAttachments($note, $this->extractNoteUploadedFiles($request));

            return $note->load(['createdByUser:id,full_name,user_name', 'attachments:id,task_note_id,file_name,file_path,mime_type,file_size']);
        });

        return response()->json([
            'message' => 'Task note added successfully.',
            'data' => [
                'note' => $this->transformTaskNote($note),
                'task' => $this->transformTask($task->fresh()->load($this->taskRelations())),
            ],
        ], 201);
    }

    public function downloadNoteAttachment(TaskNoteAttachment $taskNoteAttachment)
    {
        $taskNoteAttachment->loadMissing('note.task');

        $path = $taskNoteAttachment->file_path;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->download(
            Storage::disk('public')->path($path),
            $taskNoteAttachment->file_name,
            array_filter([
                'Content-Type' => $taskNoteAttachment->mime_type,
            ]),
        );
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
            'reminder_at' => ['nullable', 'date', 'before_or_equal:scheduled_at'],
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
            'reminder_at' => $this->normalizeReminderAt($payload),
            'reminder_offset_minutes' => isset($payload['reminder_offset_minutes']) && $payload['reminder_offset_minutes'] !== ''
                ? (int) $payload['reminder_offset_minutes']
                : null,
            'reminder_channels' => array_values(array_filter((array) ($payload['reminder_channels'] ?? ($reminder['channels'] ?? [])))),
        ];
    }

    private function normalizeReminderAt(array $payload): ?string
    {
        $reminderAt = $payload['reminder_at'] ?? ($payload['reminder']['dateTime'] ?? ($payload['reminderAt'] ?? null));
        if (is_string($reminderAt) && trim($reminderAt) !== '') {
            return $reminderAt;
        }

        if (
            isset($payload['reminder_offset_minutes'])
            && $payload['reminder_offset_minutes'] !== ''
            && ! empty($payload['scheduled_at'])
        ) {
            try {
                return Carbon::parse($payload['scheduled_at'])
                    ->subMinutes((int) $payload['reminder_offset_minutes'])
                    ->toDateTimeString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
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

    private function storeNoteAttachments(TaskNote $note, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('task-note-attachments', 'public');

            TaskNoteAttachment::create([
                'task_note_id' => $note->id,
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
            'lead:id,client_id,business_entity_id,kam_id',
            'lead.client:id,client_name',
            'lead.businessEntity:id,name',
            'lead.kam:id,full_name,user_name',
            'client:id,client_name,business_entity_id',
            'client.businessEntity:id,name',
            'assignedToUser:id,full_name,user_name',
            'creator:id,full_name,user_name',
            'taskType:id,name',
            'reminderChannels:id,task_id,channel',
            'attachments:id,task_id,file_name,file_path,mime_type,file_size',
            'notes.createdByUser:id,full_name,user_name',
            'notes.attachments:id,task_note_id,file_name,file_path,mime_type,file_size',
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
            'business_entity_id' => $task->lead?->business_entity_id ?? $task->client?->business_entity_id,
            'business_entity_name' => $task->lead?->businessEntity?->name ?? $task->client?->businessEntity?->name,
            'client_id' => $task->client_id,
            'client' => $task->client?->client_name,
            'kam_id' => $task->lead?->kam_id,
            'kam_name' => $task->lead?->kam?->full_name ?? $task->lead?->kam?->user_name,
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
            'reminderAt' => $this->resolveReminderAt($task)?->toIso8601String(),
            'reminder_at' => $this->resolveReminderAt($task)?->toIso8601String(),
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
            'notes' => $task->notes?->map(fn (TaskNote $note) => $this->transformTaskNote($note))->values()->all() ?? [],
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

    private function normalizeNotePayload(Request $request): array
    {
        return [
            'content' => trim((string) $request->input('content', $request->input('noteText'))),
        ];
    }

    private function extractNoteUploadedFiles(Request $request): array
    {
        $files = $request->file('attachments');

        if (! $files) {
            return [];
        }

        return is_array($files) ? $files : [$files];
    }

    private function transformTaskNote(TaskNote $note): array
    {
        return [
            'id' => $note->id,
            'task_id' => $note->task_id,
            'author' => $note->createdByUser?->full_name ?? $note->createdByUser?->user_name ?? 'System User',
            'createdByUserName' => $note->createdByUser?->full_name ?? $note->createdByUser?->user_name,
            'content' => $note->content,
            'attachments' => $note->attachments?->map(fn (TaskNoteAttachment $attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->file_name,
                'file_name' => $attachment->file_name,
                'file_path' => $attachment->file_path,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
                'download_url' => '/tasks/note-attachments/'.$attachment->id,
            ])->values()->all() ?? [],
            'createdAt' => $note->created_at,
            'updatedAt' => $note->updated_at,
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

    private function resolveReminderAt(Task $task): ?Carbon
    {
        if ($task->reminder_at) {
            return Carbon::parse($task->reminder_at);
        }

        if ($task->scheduled_at && $task->reminder_offset_minutes !== null) {
            return Carbon::parse($task->scheduled_at)->subMinutes((int) $task->reminder_offset_minutes);
        }

        return null;
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

    private function taskCalendarQuery(array $filters, ?User $user)
    {
        $query = $this->taskCalendarScope((int) ($user?->id ?? 0))
            ->select('tasks.*')
            ->with($this->taskRelations());

        if (! empty($filters['date_from'])) {
            $query->whereDate('tasks.scheduled_at', '>=', Carbon::parse($filters['date_from'])->toDateString());
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('tasks.scheduled_at', '<=', Carbon::parse($filters['date_to'])->toDateString());
        }

        if (! empty($filters['business_entity_id'])) {
            $businessEntityId = (int) $filters['business_entity_id'];

            $query->where(function ($builder) use ($businessEntityId): void {
                $builder->where('leads_sort.business_entity_id', $businessEntityId)
                    ->orWhere('task_clients_sort.business_entity_id', $businessEntityId);
            });
        }

        if (! empty($filters['team_id'])) {
            $teamUserIds = $this->resolveUserIdsByTeam((int) $filters['team_id']);
            $query->whereIn('tasks.assigned_to_user_id', $teamUserIds !== [] ? $teamUserIds : [0]);
        }

        if (! empty($filters['group_id'])) {
            $groupUserIds = $this->resolveUserIdsByGroup((int) $filters['group_id']);
            $query->whereIn('tasks.assigned_to_user_id', $groupUserIds !== [] ? $groupUserIds : [0]);
        }

        if (! empty($filters['kam_id'])) {
            $query->where('leads_sort.kam_id', (int) $filters['kam_id']);
        }

        return $query
            ->orderBy('tasks.scheduled_at')
            ->orderBy('tasks.id');
    }

    private function taskCalendarScope(int $userId)
    {
        $query = Task::query()
            ->leftJoin('leads as leads_sort', 'leads_sort.id', '=', 'tasks.lead_id')
            ->leftJoin('clients as task_clients_sort', 'task_clients_sort.id', '=', 'tasks.client_id');

        $visibleUserIds = $this->resolveVisibleTaskUserIds($userId);

        if ($visibleUserIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($builder) use ($visibleUserIds, $userId): void {
            $builder->whereIn('tasks.assigned_to_user_id', $visibleUserIds);

            if ($userId > 0) {
                $builder->orWhere(function ($inner) use ($userId): void {
                    $inner->whereNull('tasks.assigned_to_user_id')
                        ->where('tasks.created_by_user_id', $userId);
                });
            }
        });
    }

    /**
     * @return array<int, int>
     */
    private function resolveVisibleTaskUserIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return collect([$userId])
            ->merge(
                collect($this->resolveUserTeamIds($userId))
                    ->flatMap(fn (int $teamId) => $this->resolveUserIdsByTeam($teamId))
            )
            ->merge(
                collect($this->resolveUserGroupIds($userId))
                    ->flatMap(fn (int $groupId) => $this->resolveUserIdsByGroup($groupId))
            )
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserIdsByTeam(int $teamId): array
    {
        return collect()
            ->merge(
                UserTeamMapping::query()
                    ->where('team_id', $teamId)
                    ->pluck('user_id')
                    ->all()
            )
            ->merge(
                UserDefaultMapping::query()
                    ->where('team_id', $teamId)
                    ->pluck('user_id')
                    ->all()
            )
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserIdsByGroup(int $groupId): array
    {
        return collect()
            ->merge(
                UserGroupMapping::query()
                    ->where('group_id', $groupId)
                    ->pluck('user_id')
                    ->all()
            )
            ->merge(
                UserDefaultMapping::query()
                    ->where('group_id', $groupId)
                    ->pluck('user_id')
                    ->all()
            )
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserTeamIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return collect()
            ->merge(
                UserTeamMapping::query()
                    ->where('user_id', $userId)
                    ->pluck('team_id')
                    ->all()
            )
            ->merge(
                UserDefaultMapping::query()
                    ->where('user_id', $userId)
                    ->pluck('team_id')
                    ->all()
            )
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserGroupIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return collect()
            ->merge(
                UserGroupMapping::query()
                    ->where('user_id', $userId)
                    ->pluck('group_id')
                    ->all()
            )
            ->merge(
                UserDefaultMapping::query()
                    ->where('user_id', $userId)
                    ->pluck('group_id')
                    ->all()
            )
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function taskCalendarBusinessEntityIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->taskCalendarScope($userId)
            ->selectRaw('DISTINCT COALESCE(leads_sort.business_entity_id, task_clients_sort.business_entity_id) as business_entity_id')
            ->pluck('business_entity_id')
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function taskCalendarKamIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->taskCalendarScope($userId)
            ->selectRaw('DISTINCT leads_sort.kam_id as kam_id')
            ->pluck('kam_id')
            ->map(fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->values()
            ->all();
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
