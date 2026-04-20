<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationFeaturePermission extends Model
{
    protected $table = 'navigation_feature_permissions';

    protected $fillable = [
        'navigation_id',
        'feature_name',
    ];
}
