<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessEntityUserMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_entity_id',
        'kam_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'business_entity_id' => 'integer',
        'kam_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns this mapping
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the business entity
     */
    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'business_entity_id');
    }

    /**
     * Get the client (KAM)
     */
    public function kam(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'kam_id');
    }
}