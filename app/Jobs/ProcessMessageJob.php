<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\MessageService;

class ProcessMessageJob implements ShouldQueue
{
    use Queueable;

    public string $channel;
    public array $payload;

    public function __construct(string $channel, array $payload)
    {
        $this->channel = $channel;
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $service = app(MessageService::class);

        $data = $service->format($this->channel, $this->payload);

        $service->save($data);
    }
}
