<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadAssign extends Model
{
    use HasFactory;

    protected $table = 'lead_assign';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
}
