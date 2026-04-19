<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_entity_id',
        'client_id',
        'client_name',
        'client_from',
        'contact_person',
        'contact_no',
        'email',
        'address',
        'lat',
        'long',
        'division_id',
        'district_id',
        'thana_id',
        'licence',
        'status',
    ];

    protected $casts = [
        'business_entity_id' => 'integer',
        'division_id' => 'integer',
        'district_id' => 'integer',
        'thana_id' => 'integer',
        'lat' => 'decimal:8',
        'long' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function thana(): BelongsTo
    {
        return $this->belongsTo(Thana::class);
    }
}
