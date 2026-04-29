<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBackofficeMapping extends Model
{
    protected $table = 'user_backoffice_mappings';

    protected $fillable = [
        'user_id',
        'backoffice_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
