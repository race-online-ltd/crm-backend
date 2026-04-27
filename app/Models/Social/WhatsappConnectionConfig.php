<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappConnectionConfig extends Model
{
    protected $fillable = [
        'connection_id',
        'app_id',
        'app_secret',
        'graph_api_version',
        'business_account_id',
        'phone_number_id',
        'display_phone_number',
        'access_token',
        'webhook_verify_token',
    ];

    protected $casts = [
        'app_secret'           => 'encrypted',
        'access_token'         => 'encrypted',
        'webhook_verify_token' => 'encrypted',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }
}