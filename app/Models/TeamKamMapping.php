<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamKamMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'kam_id',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function kam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kam_id');
    }
}
