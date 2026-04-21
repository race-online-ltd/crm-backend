<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name',
        'user_name',
        'email',
        'phone',
        'password',
        'role_id',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function kamProductMappings(): HasMany
    {
        return $this->hasMany(KamProductMapping::class, 'user_id');
    }

    public function externalUserMappings(): HasMany
    {
        return $this->hasMany(InternalExternalUserMap::class, 'user_id');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function getPermissionKeys()
    {
        return DB::table('role_permissions as rp')
            ->join('permission_actions as pa', 'pa.id', '=', 'rp.navigation_permission_id')
            ->where('rp.role_id', $this->role_id)
            ->pluck('pa.key')
            ->toArray();
    }
}
