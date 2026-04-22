<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureActionPermission extends Model
{
    use HasFactory;

    protected $table = 'feature_action_permissions';

    protected $fillable = [
        'user_view_id',
        'read',
        'write',
        'modify',
        'delete',
    ];

    protected $casts = [
        'read' => 'boolean',
        'write' => 'boolean',
        'modify' => 'boolean',
        'delete' => 'boolean',
    ];

    /**
     * Relationship (assuming user_view_id refers to another table)
     */
    public function userView()
    {
        return $this->belongsTo(UserViewPermission::class, 'user_view_id');
    }
}
