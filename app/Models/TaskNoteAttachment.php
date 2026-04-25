<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskNoteAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_note_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'task_note_id' => 'integer',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(TaskNote::class, 'task_note_id');
    }
}
