<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationPermission extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'navigation_item_id',
        'permission_action_id',
    ];

    protected $casts = [
        'navigation_item_id' => 'integer',
        'permission_action_id' => 'integer',
    ];

    public function navigationItem(): BelongsTo
    {
        return $this->belongsTo(NavigationItem::class);
    }

    public function permissionAction(): BelongsTo
    {
        return $this->belongsTo(PermissionAction::class);
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }
}
