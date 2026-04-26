<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailConnectionConfig extends Model
{
    protected $fillable = [
        'connection_id',
        'provider_label',
        'email_address',
        'imap_host',
        'imap_port',
        'imap_username',
        'imap_password',
        'encryption',
        'mailbox',
        'polling_interval_seconds',
        'last_polled_at',
    ];

    protected $casts = [
        'imap_port'                => 'integer',
        'polling_interval_seconds' => 'integer',
        'last_polled_at'           => 'datetime',
        'imap_password'            => 'encrypted',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }
}