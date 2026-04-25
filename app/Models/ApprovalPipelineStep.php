<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalPipelineStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_entity_id',
        'user_id',
        'level',
    ];

    protected $casts = [
        'business_entity_id' => 'integer',
        'user_id' => 'integer',
        'level' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'business_entity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
