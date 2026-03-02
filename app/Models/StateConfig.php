<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StateConfig extends Model
{
    use HasFactory;

    protected $table = 'state_configs';

    protected $fillable = [
        'sensor_id',
        'path',
        'value',
        'name',
        'attache_sound',
        'url',
        'color',
        'sound',
        'blink'
    ];

//    protected $casts = [
//        'timestamp' => 'datetime',
//    ];

    public function sensor()
    {
        return $this->belongsTo(SensorList::class);
    }
}
