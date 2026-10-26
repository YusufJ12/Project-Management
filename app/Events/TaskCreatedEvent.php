<?php

namespace App\Events;

use App\Models\Task;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCreatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $spaceId;
    public Task $task;
    public array $actor;

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task, User|array $actor, ?int $spaceId = null)
    {
        $this->task = $task;
        $this->actor = is_array($actor) ? $actor : [
            'id' => $actor->id,
            'name' => $actor->name,
        ];

        if ($spaceId) {
            $this->spaceId = (int) $spaceId;
        } else {
            $this->spaceId = (int) ($task->list?->space_id ?? $task->load('list')->list?->space_id);
        }
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
            'task' => $this->task,
            'actor' => [
                'id' => $this->actor['id'] ?? null,
                'name' => $this->actor['name'] ?? null,
            ],
        ];
    }
}
