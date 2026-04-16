<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'team_name',
        'business_entity_id',
        'supervisor_id',
        'kam_id',
        'status',
    ];

    protected $casts = [
        'business_entity_id' => 'array',
        'supervisor_id' => 'array',
        'kam_id' => 'array',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function supervisorMappings(): HasMany
    {
        return $this->hasMany(TeamSupervisorMapping::class);
    }

    public function kamMappings(): HasMany
    {
        return $this->hasMany(TeamKamMapping::class);
    }
}
