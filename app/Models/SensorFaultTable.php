<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensorFaultTable extends Model
{
    use HasFactory;

    protected $table = 'sensor_fault_tables';

    protected $fillable = [
        'sensor_id',
        'value',
        'acknowledgement_status',
        'checked_by',
        'description',

    ];
}
