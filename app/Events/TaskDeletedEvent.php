<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskDeletedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $spaceId;
    public int $taskId;
    public int $stageId;
    public array $actor;

    /**
     * Create a new event instance.
     */
    public function __construct(int $spaceId, int $taskId, int $stageId, User|array $actor)
    {
        $this->spaceId = (int) $spaceId;
        $this->taskId = (int) $taskId;
        $this->stageId = (int) $stageId;
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
            'stageId' => $this->stageId,
            'actor' => [
                'id' => $this->actor['id'] ?? null,
                'name' => $this->actor['name'] ?? null,
            ],
        ];
    }
}
