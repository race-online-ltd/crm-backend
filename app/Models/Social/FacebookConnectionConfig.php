<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookConnectionConfig extends Model
{
    protected $fillable = [
        'connection_id',
        'app_id',
        'app_secret',
        'graph_api_version',
        'page_id',
        'page_name',
        'webhook_verify_token',
        'page_access_token',
    ];

    protected $casts = [
        'app_secret'           => 'encrypted',
        'webhook_verify_token' => 'encrypted',
        'page_access_token'    => 'encrypted',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }
}