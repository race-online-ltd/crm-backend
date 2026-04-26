<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BusinessEntity;

class ChannelConnectionActive extends Model
{
    protected $table = 'channel_connection_active';

    public $timestamps = false;

    protected $fillable = [
        'business_entity_id',
        'channel_key',
        'connection_id',
        'set_at',
    ];

    protected $casts = [
        'set_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }
}
