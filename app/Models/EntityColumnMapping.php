<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntityColumnMapping extends Model
{


    protected $table = 'entity_column_mappings';
    
    protected $fillable = [
        'entity_id',
        'page_id',
        'table_name',
        'table_id',
        'column_name',
        'column_id',
    ];
}
