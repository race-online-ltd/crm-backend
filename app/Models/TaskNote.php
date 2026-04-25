<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'created_by_user_id',
        'content',
    ];

    protected $casts = [
        'task_id' => 'integer',
        'created_by_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskNoteAttachment::class, 'task_note_id');
    }
}
