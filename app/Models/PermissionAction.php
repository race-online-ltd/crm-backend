<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionAction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'key',
        'label',
    ];

    public function navigationPermissions(): HasMany
    {
        return $this->hasMany(NavigationPermission::class);
    }
}
