<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KamTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_entity_id',
        'kam_id',
        'product_id',
        'target_mode',
        'target_value',
        'target_year',
        'revenue_target',
        'created_by',
    ];

    protected $casts = [
        'target_value' => 'integer',
        'target_year' => 'integer',
        'revenue_target' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'business_entity_id');
    }

    public function kam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kam_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
