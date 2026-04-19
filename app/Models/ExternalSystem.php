<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalSystem extends Model
{
    use HasFactory;

    protected $table = 'external_system';

    protected $fillable = [
        'external_system_name',
        'external_system_api',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function userMappings(): HasMany
    {
        return $this->hasMany(InternalExternalUserMap::class, 'external_system_id');
    }
}
