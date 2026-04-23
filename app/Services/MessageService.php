<?php

namespace App\Services;

use App\Models\Message;

class MessageService
{
    public function format(string $channel, array $payload): array
    {
        return match ($channel) {
            'facebook' => app(FacebookService::class)->format($payload),
            'whatsapp' => app(WhatsAppService::class)->format($payload),
            'email' => app(EmailService::class)->format($payload),
            default => throw new \Exception('Invalid channel'),
        };
    }

    public function save(array $data): void
    {
        Message::create([
            'sender' => $data['sender'] ?? null,
            'message' => $data['message'] ?? '',
            'channel' => $data['channel'],
        ]);
    }
}
