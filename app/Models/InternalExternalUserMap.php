<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalExternalUserMap extends Model
{
    use HasFactory;

    protected $table = 'internal_external_user_map';

    protected $fillable = [
        'user_id',
        'external_user_id',
        'external_system_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function externalSystem(): BelongsTo
    {
        return $this->belongsTo(ExternalSystem::class, 'external_system_id');
    }
}
