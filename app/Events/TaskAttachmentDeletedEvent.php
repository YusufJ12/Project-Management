<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAttachmentDeletedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $spaceId;
    public int $taskId;
    public int $attachmentId;
    public array $actor;

    /**
     * Create a new event instance.
     */
    public function __construct(int $spaceId, int $taskId, int $attachmentId, User|array $actor)
    {
        $this->spaceId = (int) $spaceId;
        $this->taskId = (int) $taskId;
        $this->attachmentId = (int) $attachmentId;
        $this->actor = is_array($actor) ? $actor : [
            'id' => $actor->id,
            'name' => $actor->name,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('space.' . $this->spaceId),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'taskId' => $this->taskId,
            'attachmentId' => $this->attachmentId,
            'actor' => [
                'id' => $this->actor['id'] ?? null,
                'name' => $this->actor['name'] ?? null,
            ],
        ];
    }
}
