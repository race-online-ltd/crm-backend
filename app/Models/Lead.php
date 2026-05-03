<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_entity_id',
        'source_id',
        'source_info',
        'lead_assign_id',
        'kam_id',
        'backoffice_id',
        'client_id',
        'lead_pipeline_stage_id',
        'expected_revenue',
        'deadline',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'business_entity_id' => 'integer',
        'source_id' => 'integer',
        'lead_assign_id' => 'integer',
        'kam_id' => 'integer',
        'backoffice_id' => 'integer',
        'client_id' => 'integer',
        'lead_pipeline_stage_id' => 'integer',
        'expected_revenue' => 'decimal:2',
        'deadline' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'business_entity_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function leadAssign(): BelongsTo
    {
        return $this->belongsTo(LeadAssign::class, 'lead_assign_id');
    }

    public function kam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kam_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->kam();
    }

    public function backoffice(): BelongsTo
    {
        return $this->belongsTo(Backoffice::class, 'backoffice_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(LeadPipelineStage::class, 'lead_pipeline_stage_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'lead_products', 'lead_id', 'product_id')
            ->withPivot('product_name')
            ->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class, 'lead_id');
    }
}
