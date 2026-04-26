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


// <?php

// namespace App\Services;

// class WhatsAppService
// {
//     /**
//      * Extract a normalised message from a WhatsApp Cloud API webhook payload.
//      * WhatsApp Cloud API structure differs from Facebook Messenger —
//      * messages live under entry[0].changes[0].value.messages[0].
//      */
//     public function format(array $payload): array
//     {
//         $value   = $payload['entry'][0]['changes'][0]['value'] ?? [];
//         $message = $value['messages'][0] ?? [];
//         $contact = $value['contacts'][0] ?? [];
//         $meta    = $value['metadata'] ?? [];

//         return [
//             'channel'           => 'whatsapp',
//             'external_id'       => $message['id'] ?? null,         // WhatsApp message ID — use for dedup
//             'sender_id'         => $message['from'] ?? null,        // sender's phone number
//             'sender_name'       => $contact['profile']['name'] ?? null,
//             'recipient_id'      => $meta['phone_number_id'] ?? null, // your phone number ID
//             'message_type'      => $message['type'] ?? 'unknown',   // text, image, audio, video, document, location, etc.
//             'message'           => $message['text']['body'] ?? null,
//             'attachments'       => $this->resolveAttachments($message),
//             'timestamp'         => isset($message['timestamp']) ? (int) $message['timestamp'] : null,
//             'raw'               => $payload,
//         ];
//     }

//     private function resolveAttachments(array $message): array
//     {
//         $type = $message['type'] ?? null;

//         if (in_array($type, ['image', 'audio', 'video', 'document', 'sticker'])) {
//             return [$message[$type] ?? []];
//         }

//         if ($type === 'location') {
//             return [$message['location'] ?? []];
//         }

//         return [];
//     }
// }