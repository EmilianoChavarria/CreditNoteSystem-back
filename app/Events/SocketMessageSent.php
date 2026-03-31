<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocketMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly array $payload
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel('notifications.global')];
    }

    public function broadcastAs(): string
    {
        return 'socket.message.sent';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
