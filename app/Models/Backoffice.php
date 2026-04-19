<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Backoffice extends Model
{
    use HasFactory;

    protected $table = 'backoffice';

    protected $fillable = [
        'backoffice_name',
        'business_entity_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'business_entity_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'backoffice_user_mapping')
            ->withTimestamps();
    }
}
