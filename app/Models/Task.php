<?php

namespace App\Models;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lead_id',
        'client_id',
        'source_id',
        'source_info',
        'assigned_to_user_id',
        'created_by_user_id',
        'assignment_mode',
        'status',
        'task_type_id',
        'title',
        'details',
        'scheduled_at',
        'location_address',
        'location_latitude',
        'location_longitude',
        'reminder_enabled',
        'reminder_at',
        'reminder_offset_minutes',
        'checked_in_at',
        'checked_in_latitude',
        'checked_in_longitude',
        'checked_in_distance_meters',
        'completed_at',
        'completion_message',
        'cancelled_at',
        'cancellation_reason',
        'updated_by',
    ];

    protected $casts = [
        'lead_id' => 'integer',
        'client_id' => 'integer',
        'source_id' => 'integer',
        'assigned_to_user_id' => 'integer',
        'created_by_user_id' => 'integer',
        'task_type_id' => 'integer',
        'status' => 'string',
        'scheduled_at' => 'datetime',
        'location_latitude' => 'decimal:7',
        'location_longitude' => 'decimal:7',
        'reminder_enabled' => 'boolean',
        'reminder_at' => 'datetime',
        'reminder_offset_minutes' => 'integer',
        'checked_in_at' => 'datetime',
        'checked_in_latitude' => 'decimal:7',
        'checked_in_longitude' => 'decimal:7',
        'checked_in_distance_meters' => 'integer',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TaskType::class, 'task_type_id');
    }

    public function reminderChannels(): HasMany
    {
        return $this->hasMany(TaskReminderChannel::class, 'task_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class, 'task_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class, 'task_id')->orderByDesc('created_at');
    }
}
