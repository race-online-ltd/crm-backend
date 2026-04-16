<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'navigation_permission_id',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'navigation_permission_id' => 'integer',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function navigationPermission(): BelongsTo
    {
        return $this->belongsTo(NavigationPermission::class);
    }
}
