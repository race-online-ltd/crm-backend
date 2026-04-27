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



// <?php

// namespace App\Services;

// use App\Models\Message;
// use App\Models\Social\ChannelConnectionActive;
// use Illuminate\Support\Facades\Log;

// class MessageService
// {
//     public function __construct(
//         private readonly FacebookService $facebookService,
//         private readonly WhatsAppService $whatsAppService,
//         private readonly EmailService    $emailService,
//     ) {}

//     /**
//      * Format a raw webhook/IMAP payload into a normalised array.
//      */
//     public function format(string $channel, array $payload): array
//     {
//         return match ($channel) {
//             'facebook' => $this->facebookService->format($payload),
//             'whatsapp' => $this->whatsAppService->format($payload),
//             'email'    => $this->emailService->format($payload),
//             default    => throw new \InvalidArgumentException("Unsupported channel: {$channel}"),
//         };
//     }

//     /**
//      * Resolve which business entity owns this incoming message
//      * by matching the channel-specific recipient ID against active connections.
//      *
//      * $recipientId is:
//      *   - Facebook:  your Page ID (messaging[0].recipient.id)
//      *   - WhatsApp:  your Phone Number ID (metadata.phone_number_id)
//      *   - Email:     the mailbox email address
//      */
//     public function resolveBusinessEntity(string $channel, string $recipientId): ?int
//     {
//         // Find the active connection whose config matches this recipient
//         $connection = match ($channel) {
//             'facebook' => \App\Models\Social\FacebookConnectionConfig::where('page_id', $recipientId)
//                 ->with('connection.activePointer')
//                 ->first()
//                 ?->connection,

//             'whatsapp' => \App\Models\Social\WhatsappConnectionConfig::where('phone_number_id', $recipientId)
//                 ->with('connection.activePointer')
//                 ->first()
//                 ?->connection,

//             'email'    => \App\Models\Social\EmailConnectionConfig::where('email_address', $recipientId)
//                 ->with('connection.activePointer')
//                 ->first()
//                 ?->connection,

//             default => null,
//         };

//         if (!$connection) {
//             Log::warning("MessageService: no connection found for {$channel} recipient {$recipientId}");
//             return null;
//         }

//         if (!$connection->is_active) {
//             Log::warning("MessageService: matched connection {$connection->id} is not active");
//             return null;
//         }

//         return $connection->business_entity_id;
//     }

//     /**
//      * Save a normalised message. Skips duplicates using external_id.
//      */
//     public function save(array $data): ?Message
//     {
//         // Deduplicate: if we've already stored this external message ID, skip it
//         if (!empty($data['external_id'])) {
//             $exists = Message::where('channel', $data['channel'])
//                 ->where('external_id', $data['external_id'])
//                 ->exists();

//             if ($exists) {
//                 Log::info("MessageService: duplicate message skipped", [
//                     'channel'     => $data['channel'],
//                     'external_id' => $data['external_id'],
//                 ]);
//                 return null;
//             }
//         }

//         // Don't store echo messages (messages sent BY your page, not received)
//         if (!empty($data['is_echo'])) {
//             return null;
//         }

//         return Message::create([
//             'business_entity_id' => $data['business_entity_id'] ?? null,
//             'connection_id'      => $data['connection_id'] ?? null,
//             'channel'            => $data['channel'],
//             'external_id'        => $data['external_id'] ?? null,
//             'sender_id'          => $data['sender_id'] ?? null,
//             'sender_name'        => $data['sender_name'] ?? null,
//             'message_type'       => $data['message_type'] ?? 'text',
//             'subject'            => $data['subject'] ?? null,
//             'message'            => $data['message'] ?? null,
//             'attachments'        => $data['attachments'] ?? [],
//             'raw'                => $data['raw'] ?? [],
//             'received_at'        => $data['received_at'] ?? now(),
//         ]);
//     }
// }