<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'group_name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function supervisorMappings(): HasMany
    {
        return $this->hasMany(GroupSupervisorMapping::class);
    }

    public function teamMappings(): HasMany
    {
        return $this->hasMany(GroupTeamMapping::class);
    }
}
