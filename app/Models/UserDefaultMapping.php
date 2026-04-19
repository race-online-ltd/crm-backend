<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDefaultMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_entity_id',
        'kam_id',
        'team_id',
        'group_id',
        'division_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'business_entity_id' => 'integer',
        'kam_id' => 'integer',
        'team_id' => 'integer',
        'group_id' => 'integer',
        'division_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'business_entity_id');
    }

    public function kam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kam_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }
}
