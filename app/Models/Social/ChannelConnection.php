<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BusinessEntity;

class ChannelConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_entity_id',
        'channel_key',
        'connection_name',
        'is_active',
        'last_active_at',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_active_at' => 'datetime',
    ];

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    public function facebookConfig(): HasOne
    {
        return $this->hasOne(FacebookConnectionConfig::class, 'connection_id');
    }

    public function whatsappConfig(): HasOne
    {
        return $this->hasOne(WhatsappConnectionConfig::class, 'connection_id');
    }

    public function emailConfig(): HasOne
    {
        return $this->hasOne(EmailConnectionConfig::class, 'connection_id');
    }

    public function activePointer(): HasOne
    {
        return $this->hasOne(ChannelConnectionActive::class, 'connection_id');
    }

    public function isCurrentlyActive(): bool
    {
        return $this->activePointer()->exists();
    }
}