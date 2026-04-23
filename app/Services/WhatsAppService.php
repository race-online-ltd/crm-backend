<?php

namespace App\Services;

class WhatsAppService
{
    public function format(array $payload): array
    {
        return [
            'sender' => $payload['messages'][0]['from'] ?? null,
            'message' => $payload['messages'][0]['text']['body'] ?? '',
            'channel' => 'whatsapp',
        ];
    }
}
