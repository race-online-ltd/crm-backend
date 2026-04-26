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



// <?php

// namespace App\Services;

// class FacebookService
// {
//     /**
//      * Extract a normalised message from a Facebook Messenger webhook payload.
//      * Facebook can deliver multiple entries and multiple messaging events per request.
//      */
//     public function format(array $payload): array
//     {
//         $entry     = $payload['entry'][0] ?? [];
//         $messaging = $entry['messaging'][0] ?? [];
//         $message   = $messaging['message'] ?? [];

//         return [
//             'channel'           => 'facebook',
//             'external_id'       => $message['mid'] ?? null,      // Facebook message ID — use for dedup
//             'sender_id'         => $messaging['sender']['id'] ?? null,
//             'recipient_id'      => $messaging['recipient']['id'] ?? null, // your page ID
//             'message_type'      => $this->resolveMessageType($message),
//             'message'           => $message['text'] ?? null,
//             'attachments'       => $message['attachments'] ?? [],
//             'is_echo'           => $message['is_echo'] ?? false,  // true = sent BY your page, not received
//             'raw'               => $payload,
//         ];
//     }

//     private function resolveMessageType(array $message): string
//     {
//         if (!empty($message['attachments'])) {
//             return $message['attachments'][0]['type'] ?? 'attachment'; // image, audio, video, file, location
//         }

//         if (isset($message['text'])) {
//             return 'text';
//         }

//         return 'unknown';
//     }
// }