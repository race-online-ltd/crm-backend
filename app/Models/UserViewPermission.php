<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserViewPermission extends Model
{
    protected $table = 'user_view_permissions';

    protected $fillable = [
        'role_id',
        'navigation_id',
        'feature_id',
    ];
}
