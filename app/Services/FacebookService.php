<?php

namespace App\Services;

class FacebookService
{
    public function format(array $payload): array
    {
        return [
            'sender' => $payload['entry'][0]['messaging'][0]['sender']['id'] ?? null,
            'message' => $payload['entry'][0]['messaging'][0]['message']['text'] ?? '',
            'channel' => 'facebook',
        ];
    }
}
