<?php

namespace App\Services;

class EmailService
{
   public function format(array $payload): array
    {
        return [
            'sender' => $payload['from'] ?? null,
            'message' => $payload['body'] ?? '',
            'channel' => 'email',
        ];
    }

}
