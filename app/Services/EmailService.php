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


// <?php

// namespace App\Services;

// class EmailService
// {
//     /**
//      * Normalise a parsed IMAP message into the standard format.
//      *
//      * Expected $payload shape (from your IMAP poller, e.g. via webklex/laravel-imap):
//      * [
//      *   'uid'         => 1234,             // IMAP UID — use for dedup
//      *   'message_id'  => '<abc@mail.com>', // Message-ID header
//      *   'from'        => 'John <john@example.com>',
//      *   'from_email'  => 'john@example.com',
//      *   'to'          => 'inbox@yourapp.com',
//      *   'subject'     => 'Hello',
//      *   'body_text'   => 'Plain text body...',
//      *   'body_html'   => '<p>HTML body...</p>',
//      *   'attachments' => [],
//      *   'date'        => '2024-01-01 10:00:00',
//      * ]
//      */
//     public function format(array $payload): array
//     {
//         return [
//             'channel'      => 'email',
//             'external_id'  => $payload['message_id'] ?? ('imap-uid-' . ($payload['uid'] ?? uniqid())), // use for dedup
//             'sender_id'    => $payload['from_email'] ?? null,
//             'sender_name'  => $payload['from'] ?? null,
//             'recipient_id' => $payload['to'] ?? null,
//             'message_type' => !empty($payload['attachments']) ? 'email_with_attachments' : 'email',
//             'subject'      => $payload['subject'] ?? null,
//             'message'      => $payload['body_text'] ?? strip_tags($payload['body_html'] ?? ''),
//             'body_html'    => $payload['body_html'] ?? null,
//             'attachments'  => $payload['attachments'] ?? [],
//             'received_at'  => $payload['date'] ?? null,
//             'raw'          => $payload,
//         ];
//     }
// }